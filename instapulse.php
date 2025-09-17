<?php
/**
 * Plugin Name: InstaPulse
 * Plugin URI: https://github.com/instawp/instapulse
 * Description: A light-weight APM tool for WordPress. Tracks plugin load times and frontend requests for performance monitoring.
 * Version: 1.3.0
 * Author: InstaWP
 * Author URI: https://instawp.com/
 * License: GPL v2 or later
 * Text Domain: instapulse
 * Update URI: https://github.com/instawp/instapulse/
 * GitHub Plugin URI: instawp/instapulse
 * GitHub Branch: main
 * Requires WP: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('INSTAPULSE_VERSION', '1.3.0');
define('INSTAPULSE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('INSTAPULSE_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Initialize GitHub update checker
if (!class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
    require_once plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php';
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$instapulseUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/instawp/instapulse/', // Replace with actual GitHub repo URL
    __FILE__,
    'instapulse'
);

// Set the branch that contains stable releases (default is 'master')
$instapulseUpdateChecker->setBranch('main');

// Optional: For private repositories, uncomment and add your GitHub personal access token
// $instapulseUpdateChecker->setAuthentication('your-github-token-here');

class InstaPulse {

    private static $instance = null;
    private $plugin_load_times = array();
    private $start_time;
    private $start_memory;
    private $profiler;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->start_time = $this->get_request_start_time();
        $this->start_memory = memory_get_usage(true);

        // Load profiler class
        require_once plugin_dir_path(__FILE__) . 'includes/class-profiler.php';
        $this->profiler = InstaPulse_Profiler::get_instance();

        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_instapulse_clear_data', array($this, 'ajax_clear_data'));

        // Check and install MU plugin if needed
        add_action('admin_init', array($this, 'check_mu_plugin'));
    }

    public function init() {
        if (!current_user_can('manage_options')) {
            return;
        }
    }

    /**
     * Check if MU plugin is installed and install if needed
     */
    public function check_mu_plugin() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Don't reinstall MU plugin if we're in the process of deactivating
        if (defined('INSTAPULSE_DEACTIVATING') && INSTAPULSE_DEACTIVATING) {
            return;
        }

        // Don't reinstall if we're on the plugins page and deactivating
        if (isset($_GET['action']) && $_GET['action'] === 'deactivate') {
            return;
        }

        $mu_status = $this->profiler->get_mu_plugin_status();

        // Install MU plugin if it doesn't exist
        if (!$mu_status['file_exists'] && $mu_status['writable']) {
            $this->install_mu_plugin();
        }
    }

    /**
     * Install the MU plugin
     */
    private function install_mu_plugin() {
        $result = $this->profiler->install_mu_plugin();
        if (is_wp_error($result)) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-warning"><p><strong>InstaPulse:</strong> ' . esc_html($result->get_error_message()) . '</p></div>';
            });
        }
    }

    private function get_request_start_time() {
        if (defined('WP_START_TIMESTAMP')) {
            return (float) WP_START_TIMESTAMP;
        }

        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return (float) $_SERVER['REQUEST_TIME_FLOAT'];
        }

        return microtime(true);
    }



    public function add_admin_menu() {
        add_management_page(
            'InstaPulse',
            'InstaPulse',
            'manage_options',
            'instapulse',
            array($this, 'admin_page')
        );
    }



    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'tools_page_instapulse') {
            return;
        }

        wp_enqueue_style(
            'instapulse-admin',
            INSTAPULSE_PLUGIN_URL . 'assets/admin.css',
            array(),
            INSTAPULSE_VERSION
        );

        wp_enqueue_script(
            'instapulse-admin',
            INSTAPULSE_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            INSTAPULSE_VERSION,
            true
        );

        wp_localize_script('instapulse-admin', 'instapulse_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('instapulse_nonce')
        ));
    }

    public function admin_page() {
        // Get data from the new profiler
        $aggregated_data = $this->profiler->get_aggregated_data();
        $latest_profile = $this->profiler->get_latest_profile();
        $recent_requests = $this->profiler->get_recent_requests();
        $assets_summary = $this->profiler->get_assets_summary();
        $performance_insights = $this->profiler->get_performance_insights();
        $mu_status = $this->profiler->get_mu_plugin_status();
        $system_info = $this->profiler->get_system_info();

        // Get slow query data
        $database = InstaPulse_Database::get_instance();

        // Check if queries table exists, if not create it
        if (!$database->queries_table_exists()) {
            $database->create_table(); // This will create both tables if needed
        }

        $slow_queries = $database->get_slow_queries(50);
        $slow_query_stats = $database->get_slow_query_stats();

        // Convert aggregated data to the format expected by admin-page.php
        $plugin_times = array(
            'plugins' => array(),
            'total_time' => $aggregated_data['avg_load_time'],
            'total_memory' => $aggregated_data['avg_memory_usage'],
            'timestamp' => current_time('mysql'),
            'sample_rate' => $aggregated_data['sample_rate'],
            'total_profiles' => $aggregated_data['total_profiles']
        );

        // Convert plugin data
        foreach ($aggregated_data['plugins'] as $plugin_name => $plugin_data) {
            $plugin_times['plugins'][$plugin_name] = array(
                'name' => $plugin_data['name'],
                'load_time' => $plugin_data['avg_time'],
                'memory_usage' => $plugin_data['avg_memory'],
                'profile_count' => $plugin_data['profile_count']
            );
        }

        include INSTAPULSE_PLUGIN_PATH . 'includes/admin-page.php';
    }

    public function ajax_clear_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'instapulse_nonce') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Clear all profiling data using the new profiler
        $this->profiler->clear_all_data();

        // Clear slow query data
        $database = InstaPulse_Database::get_instance();
        $database->clear_all_query_data();

        // Clear old APM Test data
        delete_option('apm_test_profiles');
        delete_transient('apm_test_latest_profile');
        $checkpoints = array('muplugins_loaded', 'plugins_loaded');
        foreach ($checkpoints as $checkpoint) {
            delete_transient('apm_test_checkpoint_' . $checkpoint);
        }

        wp_send_json_success();
    }
}

function instapulse_init() {
    return InstaPulse::get_instance();
}

add_action('plugins_loaded', 'instapulse_init');

register_activation_hook(__FILE__, function() {
    add_option('instapulse_plugin_times', array());

    // Create database tables (both profiles and queries)
    require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';
    $database = InstaPulse_Database::get_instance();
    $database->create_table();

    // Ensure both tables exist
    if (!$database->queries_table_exists()) {
        $database->create_table(); // This will create both tables if they don't exist
    }

    // Install MU plugin
    require_once plugin_dir_path(__FILE__) . 'includes/class-profiler.php';
    $profiler = InstaPulse_Profiler::get_instance();
    $profiler->install_mu_plugin();

    // Ensure this plugin loads first to capture all other plugins
    $plugin_basename = plugin_basename(__FILE__);
    $active_plugins = get_option('active_plugins', array());

    // Remove from current position
    $active_plugins = array_values(array_diff($active_plugins, array($plugin_basename)));

    // Add to beginning
    array_unshift($active_plugins, $plugin_basename);

    update_option('active_plugins', $active_plugins);
});

register_deactivation_hook(__FILE__, function() {
    // Signal that we're deactivating to prevent MU plugin reinstallation
    if (!defined('INSTAPULSE_DEACTIVATING')) {
        define('INSTAPULSE_DEACTIVATING', true);
    }

    delete_option('instapulse_plugin_times');

    // Uninstall MU plugin - simple approach
    $mu_file = WPMU_PLUGIN_DIR . '/0-instapulse.php';

    if (file_exists($mu_file)) {
        // Clear file stat cache first
        clearstatcache(true, $mu_file);

        // Force delete with multiple attempts
        for ($i = 0; $i < 3; $i++) {
            if (@unlink($mu_file)) {
                break;
            }
            // If direct unlink fails, truncate and try again
            if ($handle = @fopen($mu_file, 'w')) {
                @fclose($handle);
                if (@unlink($mu_file)) {
                    break;
                }
            }
            // Wait and clear cache before next attempt
            usleep(200000); // 200ms
            clearstatcache(true, $mu_file);
        }
    }
});

// Plugin deletion hook - removes all data and tables
register_uninstall_hook(__FILE__, 'instapulse_uninstall');

function instapulse_uninstall() {
    global $wpdb;

    // Remove all plugin options
    delete_option('instapulse_plugin_times');
    delete_option('instapulse_options');
    delete_option('instapulse_db_version');

    // Remove database tables
    require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';
    $database = InstaPulse_Database::get_instance();

    // Drop all three tables
    $wpdb->query("DROP TABLE IF EXISTS " . $database->get_assets_table_name());
    $wpdb->query("DROP TABLE IF EXISTS " . $database->get_queries_table_name());
    $wpdb->query("DROP TABLE IF EXISTS " . $database->get_table_name());

    // Remove MU plugin with retry logic
    $mu_file = WPMU_PLUGIN_DIR . '/0-instapulse.php';
    if (file_exists($mu_file)) {
        clearstatcache(true, $mu_file);
        for ($i = 0; $i < 3; $i++) {
            if (@unlink($mu_file)) {
                break;
            }
            // If direct unlink fails, truncate and try again
            if ($handle = @fopen($mu_file, 'w')) {
                @fclose($handle);
                if (@unlink($mu_file)) {
                    break;
                }
            }
            // Wait and clear cache before next attempt
            usleep(200000); // 200ms
            clearstatcache(true, $mu_file);
        }
    }

    // Clean up all transients and temporary data
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_instapulse_%' OR option_name LIKE '_transient_timeout_instapulse_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_apm_test_%' OR option_name LIKE '_transient_timeout_apm_test_%'");

    // Clean up any user meta data (if any)
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'instapulse_%'");

    // Clean up any site options for multisite
    if (is_multisite()) {
        delete_site_option('instapulse_plugin_times');
        delete_site_option('instapulse_options');
        delete_site_option('instapulse_db_version');
    }
}