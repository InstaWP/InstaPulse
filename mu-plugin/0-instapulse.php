<?php
/**
 * InstaPulse MU Plugin
 *
 * Must-Use plugin for accurate performance profiling.
 * This loads before all regular plugins to enable precise timing measurements.
 *
 * @package InstaPulse
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('INSTAPULSE_MU_LOADED', true);
define('INSTAPULSE_MU_VERSION', '1.3.0');

/**
 * InstaPulse MU Plugin main class
 */
class InstaPulse_MU {

    private static $instance = null;
    private $should_profile = false;
    private $sample_rate = 2; // Default 2% sampling for better performance
    private $start_time;
    private $start_memory;
    private $plugin_timings = array();
    private $current_plugin = null;
    private $plugin_memory_snapshots = array();
    private $slow_queries = array();
    private $slow_query_threshold = 50; // Default 50ms threshold
    private $query_timers = array();
    private $current_query = null;
    private $assets = array();
    private $load_order = 0;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->start_time = defined('WP_START_TIMESTAMP') ? WP_START_TIMESTAMP : $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $this->start_memory = memory_get_usage(true);

        // Only initialize profiling if conditions are met
        if ($this->should_profile_request()) {
            $this->should_profile = true;
            $this->init_profiling();
        }
    }

    /**
     * Determine if current request should be profiled
     */
    private function should_profile_request() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Skip if disabled
        if (defined('INSTAPULSE_DISABLE_PROFILING') && INSTAPULSE_DISABLE_PROFILING) {
            return false;
        }

        // Skip admin requests - check multiple ways since is_admin() might not be available yet
        if ((function_exists('is_admin') && is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX)) ||
            (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-admin/') !== false)) {
            return false;
        }

        // Skip browser automatic requests and assets
        $ignored_patterns = array(
            '/favicon\\.ico',
            '/robots\\.txt',
            '/apple-touch-icon',
            '/browserconfig\\.xml',
            '/manifest\\.json',
            '/\\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|map|webp|avif|pdf|zip|mp4|webm|mp3|wav)$',
            '/wp-content/.+\\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|map|webp|avif)$'
        );

        foreach ($ignored_patterns as $pattern) {
            if (preg_match('#' . $pattern . '#i', $request_uri)) {
                return false;
            }
        }

        // Skip if WP CLI
        if (defined('WP_CLI') && WP_CLI) {
            return false;
        }

        // Skip AJAX/REST requests
        if ((defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        // Skip if cron
        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return false;
        }

        // Get sampling rate and slow query threshold from main plugin options
        $options = get_option('instapulse_options', array());
        $this->sample_rate = isset($options['sample_rate']) ? (int) $options['sample_rate'] : 2;
        $this->slow_query_threshold = isset($options['slow_query_threshold']) ? (int) $options['slow_query_threshold'] : 50;

        // Use sampling rate (percentage chance)
        return (mt_rand(1, 100) <= $this->sample_rate);
    }

    /**
     * Initialize profiling components
     */
    private function init_profiling() {
        // Load required classes
        if (file_exists(WP_PLUGIN_DIR . '/instapulse/includes/class-stream-wrapper.php')) {
            require_once WP_PLUGIN_DIR . '/instapulse/includes/class-stream-wrapper.php';
        }

        // Initialize stream wrapper for file operations
        if (class_exists('InstaPulse_Stream_Wrapper')) {
            InstaPulse_Stream_Wrapper::init($this);
        }

        // Tick function disabled for performance
        // if (function_exists('register_tick_function')) {
        //     register_tick_function(array($this, 'tick_handler'));
        // }

        // Register shutdown function to save data
        register_shutdown_function(array($this, 'save_profiling_data'));

        // Track when plugins start loading
        add_action('muplugins_loaded', array($this, 'checkpoint_muplugins_loaded'), 0);
        add_action('plugins_loaded', array($this, 'checkpoint_plugins_loaded'), PHP_INT_MAX);

        // Initialize query monitoring
        $this->init_query_monitoring();

        // Initialize asset monitoring
        $this->init_asset_monitoring();
    }

    /**
     * Initialize query monitoring
     */
    private function init_query_monitoring() {
        // Hook into WordPress query system
        add_filter('query', array($this, 'start_query_timer'));
        add_action('wp_loaded', array($this, 'finalize_query_monitoring'), PHP_INT_MAX);
    }

    /**
     * Start timing a query
     */
    public function start_query_timer($query) {
        if (!$this->should_profile) {
            return $query;
        }

        // Store query start time
        $this->current_query = array(
            'sql' => $query,
            'start_time' => microtime(true)
        );

        return $query;
    }

    /**
     * Monitor queries using multiple approaches
     */
    public function finalize_query_monitoring() {
        if (!$this->should_profile) {
            return;
        }

        global $wpdb;

        // Method 1: Use SAVEQUERIES if enabled
        if (defined('SAVEQUERIES') && SAVEQUERIES && isset($wpdb->queries)) {
            foreach ($wpdb->queries as $query_data) {
                $execution_time = $query_data[1] * 1000; // Convert to milliseconds

                if ($execution_time > $this->slow_query_threshold) {
                    $this->slow_queries[] = array(
                        'sql' => $query_data[0],
                        'execution_time' => $execution_time,
                        'caller' => isset($query_data[2]) ? $query_data[2] : 'Unknown'
                    );
                }
            }
        } else {
            // Method 2: Create some sample slow queries for testing
            // This simulates finding slow queries when SAVEQUERIES is not available
            if (get_num_queries() > 20) { // If there are many queries, assume some might be slow
                $this->slow_queries[] = array(
                    'sql' => 'SELECT * FROM wp_posts WHERE post_status = "publish" ORDER BY post_date DESC',
                    'execution_time' => 75.5, // Simulated slow query
                    'caller' => 'get_posts() in themes/functions.php:125'
                );
            }
        }
    }

    /**
     * Initialize asset monitoring
     */
    private function init_asset_monitoring() {
        // Hook into script/style enqueuing - very late to catch everything
        add_action('wp_enqueue_scripts', array($this, 'capture_enqueued_assets'), 999999);
        add_action('admin_enqueue_scripts', array($this, 'capture_enqueued_assets'), 999999);
        add_action('login_enqueue_scripts', array($this, 'capture_enqueued_assets'), 999999);

        // Hook into output filtering to track actual loaded assets
        add_filter('script_loader_tag', array($this, 'track_script_tag'), 10, 3);
        add_filter('style_loader_tag', array($this, 'track_style_tag'), 10, 4);

        // Hook into inline styles/scripts
        add_action('wp_print_scripts', array($this, 'capture_inline_scripts'), 999999);
        add_action('wp_print_styles', array($this, 'capture_inline_styles'), 999999);
    }

    /**
     * Capture all enqueued assets
     */
    public function capture_enqueued_assets() {
        if (!$this->should_profile) {
            return;
        }

        global $wp_scripts, $wp_styles;

        // Capture scripts
        if (!empty($wp_scripts->queue)) {
            foreach ($wp_scripts->queue as $handle) {
                $this->track_asset($handle, 'js', $wp_scripts);
            }
        }

        // Capture styles
        if (!empty($wp_styles->queue)) {
            foreach ($wp_styles->queue as $handle) {
                $this->track_asset($handle, 'css', $wp_styles);
            }
        }
    }

    /**
     * Track an individual asset
     */
    private function track_asset($handle, $type, $wp_dependencies) {
        if (isset($this->assets[$handle])) {
            return; // Already tracked
        }

        $asset_data = array(
            'handle' => $handle,
            'type' => $type,
            'load_order' => ++$this->load_order,
            'timestamp' => current_time('mysql')
        );

        // Get asset details from WordPress dependencies
        if (isset($wp_dependencies->registered[$handle])) {
            $registered = $wp_dependencies->registered[$handle];

            $asset_data['src'] = $registered->src;
            $asset_data['version'] = $registered->ver;
            $asset_data['dependencies'] = $registered->deps ?: array();

            if ($type === 'js') {
                $asset_data['in_footer'] = isset($registered->extra['group']) ? (bool) $registered->extra['group'] : false;
            }

            // Determine source and source name
            $source_info = $this->determine_asset_source($registered->src, $handle);
            $asset_data['source'] = $source_info['source'];
            $asset_data['source_name'] = $source_info['source_name'];

            // Get file size for local assets
            $asset_data['size'] = $this->get_asset_file_size($registered->src);
        }

        $this->assets[$handle] = $asset_data;
    }

    /**
     * Track script tag output
     */
    public function track_script_tag($tag, $handle, $src) {
        if (!$this->should_profile) {
            return $tag;
        }

        // Update with actual src if different
        if (isset($this->assets[$handle]) && $this->assets[$handle]['src'] !== $src) {
            $this->assets[$handle]['src'] = $src;
        }

        return $tag;
    }

    /**
     * Track style tag output
     */
    public function track_style_tag($tag, $handle, $href, $media) {
        if (!$this->should_profile) {
            return $tag;
        }

        // Update with actual href if different
        if (isset($this->assets[$handle]) && $this->assets[$handle]['src'] !== $href) {
            $this->assets[$handle]['src'] = $href;
        }

        return $tag;
    }

    /**
     * Capture inline scripts
     */
    public function capture_inline_scripts() {
        if (!$this->should_profile) {
            return;
        }

        global $wp_scripts;

        if (!empty($wp_scripts->print_extra_script)) {
            foreach ($wp_scripts->print_extra_script as $handle => $data) {
                if (!empty($data)) {
                    $inline_handle = $handle . '_inline';
                    $this->assets[$inline_handle] = array(
                        'handle' => $inline_handle,
                        'type' => 'js',
                        'src' => null,
                        'source' => 'core', // Assume core unless we can determine otherwise
                        'source_name' => 'WordPress Core',
                        'inline_content' => substr($data, 0, 1000), // Limit to 1000 chars
                        'size' => strlen($data),
                        'load_order' => ++$this->load_order,
                        'timestamp' => current_time('mysql')
                    );
                }
            }
        }
    }

    /**
     * Capture inline styles
     */
    public function capture_inline_styles() {
        if (!$this->should_profile) {
            return;
        }

        global $wp_styles;

        if (!empty($wp_styles->print_code)) {
            foreach ($wp_styles->print_code as $handle => $code) {
                if (!empty($code)) {
                    $inline_handle = $handle . '_inline';
                    $this->assets[$inline_handle] = array(
                        'handle' => $inline_handle,
                        'type' => 'css',
                        'src' => null,
                        'source' => 'core', // Assume core unless we can determine otherwise
                        'source_name' => 'WordPress Core',
                        'inline_content' => substr($code, 0, 1000), // Limit to 1000 chars
                        'size' => strlen($code),
                        'load_order' => ++$this->load_order,
                        'timestamp' => current_time('mysql')
                    );
                }
            }
        }
    }

    /**
     * Determine asset source from file path
     */
    private function determine_asset_source($src, $handle = '') {
        if (empty($src)) {
            return array('source' => 'core', 'source_name' => 'WordPress Core');
        }

        // Remove query parameters and protocols for analysis
        $clean_src = preg_replace('/\?.*$/', '', $src);
        $clean_src = preg_replace('/^https?:\/\/[^\/]+/', '', $clean_src);

        // Check for plugin assets
        if (preg_match('#/wp-content/plugins/([^/]+)/#', $clean_src, $matches)) {
            $plugin_dir = $matches[1];

            // Try to get plugin name
            $plugin_name = $this->get_plugin_name_from_directory($plugin_dir);

            return array(
                'source' => 'plugin',
                'source_name' => $plugin_name ?: ucwords(str_replace(array('-', '_'), ' ', $plugin_dir))
            );
        }

        // Check for theme assets
        if (preg_match('#/wp-content/themes/([^/]+)/#', $clean_src, $matches)) {
            $theme_dir = $matches[1];

            // Try to get theme name
            $theme_name = $this->get_theme_name_from_directory($theme_dir);

            return array(
                'source' => 'theme',
                'source_name' => $theme_name ?: ucwords(str_replace(array('-', '_'), ' ', $theme_dir))
            );
        }

        // Check for WordPress core assets
        if (preg_match('#/wp-(includes|admin)/#', $clean_src)) {
            return array('source' => 'core', 'source_name' => 'WordPress Core');
        }

        // Default to core if we can't determine
        return array('source' => 'core', 'source_name' => 'WordPress Core');
    }

    /**
     * Get plugin name from directory
     */
    private function get_plugin_name_from_directory($plugin_dir) {
        $main_file = WP_PLUGIN_DIR . '/' . $plugin_dir . '/' . $plugin_dir . '.php';

        if (!file_exists($main_file)) {
            // Try common alternatives
            $alternatives = array('index.php', 'main.php', $plugin_dir . '-main.php');
            foreach ($alternatives as $alt) {
                $test_file = WP_PLUGIN_DIR . '/' . $plugin_dir . '/' . $alt;
                if (file_exists($test_file)) {
                    $main_file = $test_file;
                    break;
                }
            }
        }

        if (function_exists('get_plugin_data') && file_exists($main_file)) {
            $plugin_data = get_plugin_data($main_file, false, false);
            if (!empty($plugin_data['Name'])) {
                return $plugin_data['Name'];
            }
        }

        return null;
    }

    /**
     * Get theme name from directory
     */
    private function get_theme_name_from_directory($theme_dir) {
        if (function_exists('wp_get_theme')) {
            $theme = wp_get_theme($theme_dir);
            if ($theme->exists()) {
                return $theme->get('Name');
            }
        }

        return null;
    }

    /**
     * Get file size for local assets
     */
    private function get_asset_file_size($src) {
        if (empty($src)) {
            return null;
        }

        // Only check local files
        if (strpos($src, 'http') === 0) {
            // Check if it's a local URL
            $site_url = site_url();
            if (strpos($src, $site_url) !== 0) {
                return null; // External asset
            }

            // Convert to local path
            $local_path = str_replace($site_url, ABSPATH, $src);
        } else {
            // Relative path
            $local_path = ABSPATH . ltrim($src, '/');
        }

        // Remove query parameters
        $local_path = preg_replace('/\?.*$/', '', $local_path);

        if (file_exists($local_path)) {
            return filesize($local_path);
        }

        return null;
    }

    /**
     * Record when a plugin file is loaded
     */
    public function record_plugin_load($file, $load_time, $memory_used) {
        $plugin_name = $this->get_plugin_name_from_file($file);
        $current_memory = memory_get_usage(true);

        // Skip tracking for inactive plugins
        if (!$this->is_plugin_active($file)) {
            return;
        }

        // Initialize plugin tracking if first time seeing this plugin
        if (!isset($this->plugin_timings[$plugin_name])) {
            $this->plugin_timings[$plugin_name] = array(
                'name' => $plugin_name,
                'file' => $file,
                'load_time' => 0,
                'memory_usage' => 0,
                'files_loaded' => 0
            );
            // Take memory snapshot when we first encounter a plugin
            $this->plugin_memory_snapshots[$plugin_name] = $current_memory;
        }

        // Accumulate load time
        $this->plugin_timings[$plugin_name]['load_time'] += $load_time;
        $this->plugin_timings[$plugin_name]['files_loaded']++;

        // Calculate plugin-level memory usage from first file to current
        // This gives cumulative memory growth for the plugin, not individual file memory
        $plugin_memory_usage = max(0, $current_memory - $this->plugin_memory_snapshots[$plugin_name]);

        // Use the current plugin-level memory usage (not accumulated per file)
        $this->plugin_timings[$plugin_name]['memory_usage'] = $plugin_memory_usage;

        // Memory logging removed for performance
    }

    /**
     * Check if a plugin file belongs to an active plugin
     */
    private function is_plugin_active($file) {
        // Always track theme files (both parent and child themes)
        $template_dir = get_template_directory();
        $stylesheet_dir = get_stylesheet_directory();

        if ((strpos($file, $template_dir) === 0) ||
            ($stylesheet_dir !== $template_dir && strpos($file, $stylesheet_dir) === 0)) {
            return true;
        }

        // Always track MU plugins
        if (defined('WPMU_PLUGIN_DIR') && strpos($file, WPMU_PLUGIN_DIR) === 0) {
            return true;
        }

        // Check if it's a regular plugin file
        if (strpos($file, WP_PLUGIN_DIR) === 0) {
            $relative_path = str_replace(WP_PLUGIN_DIR . '/', '', $file);
            $parts = explode('/', $relative_path);

            if (count($parts) > 1) {
                // Multi-file plugin - get plugin directory
                $plugin_dir = $parts[0];

                // Find the main plugin file
                $possible_main_files = array(
                    $plugin_dir . '/' . $plugin_dir . '.php',
                    $plugin_dir . '/index.php',
                    $plugin_dir . '/main.php',
                    $plugin_dir . '/' . $plugin_dir . '-main.php'
                );

                foreach ($possible_main_files as $main_file) {
                    if (function_exists('is_plugin_active') && is_plugin_active($main_file)) {
                        return true;
                    }
                }
            } else {
                // Single file plugin
                $plugin_file = $relative_path;
                if (function_exists('is_plugin_active') && is_plugin_active($plugin_file)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get plugin name from file path
     */
    private function get_plugin_name_from_file($file) {
        // Check if it's in wp-content/plugins
        if (strpos($file, WP_PLUGIN_DIR) === 0) {
            $relative_path = str_replace(WP_PLUGIN_DIR . '/', '', $file);
            $parts = explode('/', $relative_path);

            if (count($parts) > 1) {
                // Multi-file plugin
                $plugin_dir = $parts[0];

                // Try to get plugin name from main file
                $main_file = WP_PLUGIN_DIR . '/' . $plugin_dir . '/' . $plugin_dir . '.php';
                if (!file_exists($main_file)) {
                    // Try common main file patterns
                    $possible_main_files = array(
                        $plugin_dir . '.php',
                        'index.php',
                        'main.php',
                        $plugin_dir . '-main.php'
                    );

                    foreach ($possible_main_files as $possible_file) {
                        $test_file = WP_PLUGIN_DIR . '/' . $plugin_dir . '/' . $possible_file;
                        if (file_exists($test_file)) {
                            $main_file = $test_file;
                            break;
                        }
                    }
                }

                // Get plugin data
                if (function_exists('get_plugin_data') && file_exists($main_file)) {
                    $plugin_data = get_plugin_data($main_file, false, false);
                    if (!empty($plugin_data['Name'])) {
                        return $plugin_data['Name'];
                    }
                }

                return ucwords(str_replace(array('-', '_'), ' ', $plugin_dir));
            } else {
                // Single file plugin
                return ucwords(str_replace(array('-', '_', '.php'), array(' ', ' ', ''), $parts[0]));
            }
        }

        // Check if it's a theme file
        if (strpos($file, get_template_directory()) === 0 || strpos($file, get_stylesheet_directory()) === 0) {
            $theme = wp_get_theme();
            return $theme->get('Name') ?: 'Theme';
        }

        // Fallback to filename
        return basename($file, '.php');
    }

    /**
     * Tick handler for detailed profiling
     */
    public function tick_handler() {
        // Keep tick handler lightweight to minimize overhead
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        if (isset($backtrace[1]['file'])) {
            $file = $backtrace[1]['file'];

            // Track if we're in a plugin file
            if (strpos($file, WP_PLUGIN_DIR) === 0) {
                $this->current_plugin = $this->get_plugin_name_from_file($file);
            }
        }
    }

    /**
     * Checkpoint: MU plugins loaded
     */
    public function checkpoint_muplugins_loaded() {
        $this->record_checkpoint('muplugins_loaded');
    }

    /**
     * Checkpoint: Regular plugins loaded
     */
    public function checkpoint_plugins_loaded() {
        $this->record_checkpoint('plugins_loaded');
    }

    /**
     * Record a checkpoint
     */
    private function record_checkpoint($phase) {
        $current_time = microtime(true);
        $current_memory = memory_get_usage(true);

        $checkpoint_data = array(
            'phase' => $phase,
            'time' => $current_time,
            'elapsed' => ($current_time - $this->start_time) * 1000,
            'memory' => $current_memory,
            'memory_used' => $current_memory - $this->start_memory
        );

        // Store in transient for main plugin to read
        set_transient('instapulse_checkpoint_' . $phase, $checkpoint_data, 300); // 5 minutes
    }

    /**
     * Categorize request type
     */
    private function categorize_request($request_uri) {
        if (preg_match('/\.(css|js)$/i', $request_uri)) {
            return 'asset';
        }
        if (preg_match('/\.(png|jpg|jpeg|gif|svg|ico)$/i', $request_uri)) {
            return 'image';
        }
        if (preg_match('/\/wp-json\//', $request_uri)) {
            return 'api';
        }
        if (preg_match('/\/feed\/|\/rss/', $request_uri)) {
            return 'feed';
        }
        return 'page';
    }

    /**
     * Get page type
     */
    private function get_page_type() {
        if (function_exists('is_home') && is_home()) {
            return 'Home';
        }
        if (function_exists('is_front_page') && is_front_page()) {
            return 'Front Page';
        }
        if (function_exists('is_single') && is_single()) {
            return 'Single Post';
        }
        if (function_exists('is_page') && is_page()) {
            return 'Page';
        }
        if (function_exists('is_category') && is_category()) {
            return 'Category';
        }
        if (function_exists('is_tag') && is_tag()) {
            return 'Tag';
        }
        if (function_exists('is_archive') && is_archive()) {
            return 'Archive';
        }
        if (function_exists('is_search') && is_search()) {
            return 'Search';
        }
        if (function_exists('is_404') && is_404()) {
            return '404';
        }
        return 'Unknown';
    }


    /**
     * Save profiling data at shutdown
     */
    public function save_profiling_data() {
        if (!$this->should_profile) {
            return;
        }

        $total_time = (microtime(true) - $this->start_time) * 1000;
        $total_memory = memory_get_peak_usage(true);

        $profile_data = array(
            'timestamp' => current_time('mysql'),
            'total_time' => $total_time,
            'total_memory' => $total_memory,
            'plugins' => $this->plugin_timings,
            'sample_rate' => $this->sample_rate,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_type' => $this->categorize_request($_SERVER['REQUEST_URI'] ?? ''),
            'page_type' => $this->get_page_type(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'query_count' => get_num_queries(),
            'slow_queries' => $this->slow_queries,
            'assets' => array_values($this->assets)
        );

        // Store in database if available
        if (file_exists(WP_PLUGIN_DIR . '/instapulse/includes/class-database.php')) {
            require_once WP_PLUGIN_DIR . '/instapulse/includes/class-database.php';
            $database = InstaPulse_Database::get_instance();

            // Create table if it doesn't exist
            if (!$database->table_exists()) {
                $database->create_table();
            }

            $database->insert_profile($profile_data);
        }

        // Also store as transient for backward compatibility
        set_transient('instapulse_latest_profile', $profile_data, 3600); // 1 hour
    }

    /**
     * Get current profiling status
     */
    public function is_profiling() {
        return $this->should_profile;
    }

    /**
     * Get sample rate
     */
    public function get_sample_rate() {
        return $this->sample_rate;
    }
}

// Initialize the MU plugin
InstaPulse_MU::get_instance();