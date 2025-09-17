<?php
/**
 * InstaPulse Profiler Class
 *
 * Handles profiling data collection and analysis
 *
 * @package InstaPulse
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main profiler class for data collection and analysis
 */
class InstaPulse_Profiler {

    private static $instance = null;
    private $database;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Load database class
        require_once plugin_dir_path(__FILE__) . 'class-database.php';
        $this->database = InstaPulse_Database::get_instance();

        // Initialize hooks only if MU plugin is active
        if (defined('INSTAPULSE_MU_LOADED')) {
            add_action('init', array($this, 'init'));
        }
    }

    /**
     * Initialize the profiler
     */
    public function init() {
        // Add settings for sampling rate
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('instapulse_settings', 'instapulse_options', array(
            'sanitize_callback' => array($this, 'sanitize_options')
        ));
    }

    /**
     * Sanitize plugin options
     */
    public function sanitize_options($options) {
        $sanitized = array();

        if (isset($options['sample_rate'])) {
            $sanitized['sample_rate'] = max(1, min(100, (int) $options['sample_rate']));
        } else {
            $sanitized['sample_rate'] = 5; // Default 5%
        }

        return $sanitized;
    }

    /**
     * Get latest profile data
     */
    public function get_latest_profile() {
        return $this->database->get_latest_profile();
    }

    /**
     * Get all stored profiles
     */
    public function get_all_profiles() {
        // This method is deprecated - use database methods directly
        return array();
    }

    /**
     * Get aggregated performance data
     */
    public function get_aggregated_data() {
        return $this->database->get_aggregated_data();
    }

    /**
     * Get current sample rate
     */
    public function get_sample_rate() {
        $options = get_option('instapulse_options', array());
        return isset($options['sample_rate']) ? (int) $options['sample_rate'] : 5;
    }

    /**
     * Calculate confidence level based on number of samples
     */
    private function calculate_confidence_level($sample_count) {
        if ($sample_count >= 100) {
            return 'High';
        } elseif ($sample_count >= 30) {
            return 'Medium';
        } elseif ($sample_count >= 10) {
            return 'Low';
        } else {
            return 'Very Low';
        }
    }

    /**
     * Get performance insights
     */
    public function get_performance_insights() {
        $data = $this->get_aggregated_data();
        $insights = array();

        if ($data['total_profiles'] < 10) {
            $insights[] = array(
                'type' => 'info',
                'message' => 'Collecting more data for better accuracy. Current confidence: ' . ($data['confidence_level'] ?? 'Low')
            );
        }

        // Identify slow plugins
        $slow_plugins = array();
        foreach ($data['plugins'] as $plugin_name => $plugin_data) {
            if ($plugin_data['avg_time'] > 50) { // > 50ms
                $slow_plugins[] = $plugin_data['name'];
            }
        }

        if (!empty($slow_plugins)) {
            $insights[] = array(
                'type' => 'warning',
                'message' => 'Slow plugins detected: ' . implode(', ', array_slice($slow_plugins, 0, 3)) .
                           (count($slow_plugins) > 3 ? ' and ' . (count($slow_plugins) - 3) . ' more' : '')
            );
        }

        // Check total load time
        if ($data['avg_load_time'] > 1000) { // > 1 second
            $insights[] = array(
                'type' => 'error',
                'message' => 'Average total load time is high (' . number_format($data['avg_load_time'], 2) . 'ms). Consider optimizing plugins.'
            );
        } elseif ($data['avg_load_time'] > 500) { // > 500ms
            $insights[] = array(
                'type' => 'warning',
                'message' => 'Average load time could be improved (' . number_format($data['avg_load_time'], 2) . 'ms).'
            );
        } else {
            $insights[] = array(
                'type' => 'success',
                'message' => 'Good performance! Average load time: ' . number_format($data['avg_load_time'], 2) . 'ms'
            );
        }

        // Memory usage insights
        if ($data['avg_memory_usage'] > 100 * 1024 * 1024) { // > 100MB
            $insights[] = array(
                'type' => 'warning',
                'message' => 'High memory usage: ' . size_format($data['avg_memory_usage'])
            );
        }

        return $insights;
    }

    /**
     * Clear all profiling data
     */
    public function clear_all_data() {
        // Clear database tables (both profiles and queries)
        $result = $this->database->clear_all_data();
        $this->database->clear_all_query_data();

        // Clear any remaining transients
        delete_transient('instapulse_latest_profile');

        // Clear checkpoints
        $checkpoints = array('muplugins_loaded', 'plugins_loaded');
        foreach ($checkpoints as $checkpoint) {
            delete_transient('instapulse_checkpoint_' . $checkpoint);
        }

        // Clear old option-based data
        delete_option('instapulse_profiles');

        return $result;
    }

    /**
     * Get MU plugin status
     */
    public function get_mu_plugin_status() {
        $mu_plugin_file = WPMU_PLUGIN_DIR . '/0-instapulse.php';

        return array(
            'loaded' => defined('INSTAPULSE_MU_LOADED'),
            'file_exists' => file_exists($mu_plugin_file),
            'writable' => is_writable(WPMU_PLUGIN_DIR),
            'version' => defined('INSTAPULSE_MU_VERSION') ? INSTAPULSE_MU_VERSION : 'Unknown'
        );
    }

    /**
     * Install/update MU plugin
     */
    public function install_mu_plugin() {
        $source_file = INSTAPULSE_PLUGIN_PATH . 'mu-plugin/0-instapulse.php';
        $target_file = WPMU_PLUGIN_DIR . '/0-instapulse.php';

        // Create mu-plugins directory if it doesn't exist
        if (!is_dir(WPMU_PLUGIN_DIR)) {
            wp_mkdir_p(WPMU_PLUGIN_DIR);
        }

        // Check if we can write
        if (!is_writable(WPMU_PLUGIN_DIR)) {
            return new WP_Error('not_writable', 'MU plugins directory is not writable');
        }

        // Copy the source file to the target location
        if (file_exists($source_file)) {
            $result = copy($source_file, $target_file);
            if (!$result) {
                return new WP_Error('copy_failed', 'Failed to copy MU plugin file');
            }
            return true;
        } else {
            return new WP_Error('source_missing', 'Source MU plugin file not found at: ' . $source_file);
        }
    }

    /**
     * Uninstall MU plugin
     */
    public function uninstall_mu_plugin() {
        $target_file = WPMU_PLUGIN_DIR . '/0-instapulse.php';

        if (file_exists($target_file)) {
            // Clear PHP's file stat cache to ensure fresh file info
            clearstatcache(true, $target_file);

            // Try multiple times with increasing delays
            $attempts = 0;
            $max_attempts = 5;

            while ($attempts < $max_attempts && file_exists($target_file)) {
                // Try to delete the file
                $result = @unlink($target_file);

                if ($result) {
                    return true;
                }

                // If unlink fails, try to truncate the file as a fallback
                $handle = @fopen($target_file, 'w');
                if ($handle) {
                    fclose($handle);
                    $result = @unlink($target_file);
                    if ($result) {
                        return true;
                    }
                }

                $attempts++;
                // Exponential backoff: 100ms, 200ms, 400ms, 800ms, 1600ms
                if ($attempts < $max_attempts) {
                    usleep(100000 * pow(2, $attempts - 1));
                    clearstatcache(true, $target_file);
                }
            }

            // If all attempts failed, return error but don't log
            if (file_exists($target_file)) {
                return new WP_Error('delete_failed', 'Failed to delete MU plugin file after multiple attempts. Please manually remove it from wp-content/mu-plugins/');
            }
        }

        return true;
    }

    /**
     * Export performance data as CSV
     */
    public function export_csv() {
        $data = $this->get_aggregated_data();

        $csv_data = array();
        $csv_data[] = array('Plugin Name', 'Average Load Time (ms)', 'Average Memory (bytes)', 'Profile Count', 'Confidence');

        foreach ($data['plugins'] as $plugin_name => $plugin_data) {
            $csv_data[] = array(
                $plugin_data['name'],
                number_format($plugin_data['avg_time'], 2),
                $plugin_data['avg_memory'],
                $plugin_data['profile_count'],
                $data['confidence_level']
            );
        }

        return $csv_data;
    }

    /**
     * Get recent requests from database (replaces option-based storage)
     */
    public function get_recent_requests($limit = 20) {
        global $wpdb;

        $table_name = $this->database->get_table_name();

        $requests = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    id,
                    request_uri as url,
                    request_type as type,
                    page_type,
                    method,
                    total_time as load_time,
                    total_memory as memory_usage,
                    query_count as queries,
                    timestamp
                 FROM {$table_name}
                 ORDER BY timestamp DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $requests ?: array();
    }

    /**
     * Get aggregated asset statistics
     */
    public function get_assets_summary($limit = 100) {
        return $this->database->get_aggregated_assets_stats($limit);
    }

    /**
     * Get assets for a specific profile/request
     */
    public function get_profile_assets($profile_id) {
        return $this->database->get_profile_assets($profile_id);
    }

    /**
     * Get system information for debugging
     */
    public function get_system_info() {
        return array(
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'active_plugins' => count(get_option('active_plugins', array())),
            'mu_plugins' => count(get_mu_plugins()),
            'theme' => wp_get_theme()->get('Name'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'tick_functions_available' => function_exists('register_tick_function'),
            'stream_wrapper_available' => function_exists('stream_wrapper_register'),
            'mu_plugin_status' => $this->get_mu_plugin_status()
        );
    }
}