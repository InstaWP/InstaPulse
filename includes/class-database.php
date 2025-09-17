<?php
/**
 * InstaPulse Database Class
 *
 * Handles database operations for profile data storage
 *
 * @package InstaPulse
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database handler for InstaPulse profiling data
 */
class InstaPulse_Database {

    private static $instance = null;
    private $table_name;
    private $queries_table_name;
    private $assets_table_name;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'instapulse_profiles';
        $this->queries_table_name = $wpdb->prefix . 'instapulse_queries';
        $this->assets_table_name = $wpdb->prefix . 'instapulse_assets';
    }

    /**
     * Create the profiles table
     */
    public function create_table() {
        global $wpdb;

        $current_version = get_option('instapulse_db_version', '1.0.0');
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create enhanced profiles table (serves as main request table)
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            total_time float NOT NULL,
            total_memory bigint(20) NOT NULL,
            plugin_data longtext NOT NULL,
            sample_rate int(11) NOT NULL DEFAULT 2,
            request_uri varchar(500) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            request_type varchar(50) DEFAULT 'page',
            page_type varchar(100) DEFAULT NULL,
            method varchar(10) DEFAULT 'GET',
            query_count int(11) DEFAULT 0,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY total_time (total_time),
            KEY created_at (created_at),
            KEY request_type (request_type)
        ) $charset_collate;";

        dbDelta($sql);

        // Create queries table (remove foreign key constraint for better compatibility)
        $sql_queries = "CREATE TABLE {$this->queries_table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) DEFAULT NULL,
            query_sql text NOT NULL,
            execution_time float NOT NULL,
            caller varchar(255) DEFAULT NULL,
            request_uri varchar(500) DEFAULT NULL,
            timestamp datetime NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY execution_time (execution_time),
            KEY timestamp (timestamp),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql_queries);

        // Create assets table
        $this->create_assets_table();

        // Store table version for future upgrades
        update_option('instapulse_db_version', '1.2.0');
    }

    /**
     * Create the assets table
     */
    public function create_assets_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->assets_table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            handle varchar(100) NOT NULL,
            src text DEFAULT NULL,
            type enum('css','js') NOT NULL,
            source enum('plugin','theme','core') NOT NULL,
            source_name varchar(255) DEFAULT NULL,
            version varchar(50) DEFAULT NULL,
            dependencies text DEFAULT NULL,
            size bigint(20) DEFAULT NULL,
            load_order int(11) DEFAULT NULL,
            in_footer tinyint(1) DEFAULT 0,
            inline_content mediumtext DEFAULT NULL,
            timestamp datetime NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY handle (handle),
            KEY type (type),
            KEY source (source),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Insert a new profile record
     */
    public function insert_profile($profile_data) {
        global $wpdb;

        $data = array(
            'timestamp' => $profile_data['timestamp'],
            'total_time' => (float) $profile_data['total_time'],
            'total_memory' => (int) $profile_data['total_memory'],
            'plugin_data' => json_encode($profile_data['plugins']),
            'sample_rate' => (int) $profile_data['sample_rate'],
            'request_uri' => isset($profile_data['request_uri']) ? substr($profile_data['request_uri'], 0, 500) : null,
            'user_agent' => isset($profile_data['user_agent']) ? $profile_data['user_agent'] : null,
            'request_type' => isset($profile_data['request_type']) ? $profile_data['request_type'] : 'page',
            'page_type' => isset($profile_data['page_type']) ? substr($profile_data['page_type'], 0, 100) : null,
            'method' => isset($profile_data['method']) ? $profile_data['method'] : 'GET',
            'query_count' => isset($profile_data['query_count']) ? (int) $profile_data['query_count'] : 0
        );

        $formats = array('%s', '%f', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d');

        $result = $wpdb->insert($this->table_name, $data, $formats);

        if ($result === false) {
            return false;
        }

        $profile_id = $wpdb->insert_id;

        // Insert slow queries if they exist
        if (isset($profile_data['slow_queries']) && !empty($profile_data['slow_queries'])) {
            $this->insert_slow_queries($profile_id, $profile_data['slow_queries'], $profile_data['request_uri']);
        }

        // Insert assets if they exist
        if (isset($profile_data['assets']) && !empty($profile_data['assets'])) {
            $this->insert_assets($profile_id, $profile_data['assets']);
        }

        return $profile_id;
    }

    /**
     * Insert slow queries for a profile
     */
    public function insert_slow_queries($profile_id, $queries, $request_uri = null) {
        global $wpdb;

        foreach ($queries as $query_data) {
            $data = array(
                'profile_id' => $profile_id,
                'query_sql' => substr($query_data['sql'], 0, 5000), // Limit query length
                'execution_time' => (float) $query_data['execution_time'],
                'caller' => isset($query_data['caller']) ? substr($query_data['caller'], 0, 255) : null,
                'request_uri' => $request_uri ? substr($request_uri, 0, 500) : null,
                'timestamp' => current_time('mysql')
            );

            $formats = array('%d', '%s', '%f', '%s', '%s', '%s');

            $result = $wpdb->insert($this->queries_table_name, $data, $formats);

            // Silently continue if query insertion fails
        }
    }

    /**
     * Insert assets for a profile
     */
    public function insert_assets($profile_id, $assets_data) {
        global $wpdb;

        foreach ($assets_data as $asset) {
            $data = array(
                'profile_id' => $profile_id,
                'handle' => substr($asset['handle'], 0, 100),
                'src' => isset($asset['src']) ? $asset['src'] : null,
                'type' => $asset['type'], // 'css' or 'js'
                'source' => $asset['source'], // 'plugin', 'theme', or 'core'
                'source_name' => isset($asset['source_name']) ? substr($asset['source_name'], 0, 255) : null,
                'version' => isset($asset['version']) ? substr($asset['version'], 0, 50) : null,
                'dependencies' => isset($asset['dependencies']) ? json_encode($asset['dependencies']) : null,
                'size' => isset($asset['size']) ? (int) $asset['size'] : null,
                'load_order' => isset($asset['load_order']) ? (int) $asset['load_order'] : null,
                'in_footer' => isset($asset['in_footer']) ? (bool) $asset['in_footer'] : false,
                'inline_content' => isset($asset['inline_content']) ? substr($asset['inline_content'], 0, 65535) : null,
                'timestamp' => current_time('mysql')
            );

            $formats = array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s');

            $result = $wpdb->insert($this->assets_table_name, $data, $formats);

            // Silently continue if asset insertion fails
        }
    }

    /**
     * Get assets for a specific profile
     */
    public function get_profile_assets($profile_id) {
        global $wpdb;

        if (!$this->assets_table_exists()) {
            return array();
        }

        $assets = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->assets_table_name}
                 WHERE profile_id = %d
                 ORDER BY load_order ASC, handle ASC",
                $profile_id
            ),
            ARRAY_A
        );

        // Decode dependencies JSON
        foreach ($assets as &$asset) {
            if (!empty($asset['dependencies'])) {
                $asset['dependencies'] = json_decode($asset['dependencies'], true);
            }
        }

        return $assets ?: array();
    }

    /**
     * Get aggregated assets statistics
     */
    public function get_aggregated_assets_stats($limit = 100) {
        global $wpdb;

        if (!$this->assets_table_exists()) {
            return array(
                'total_assets' => 0,
                'avg_css_count' => 0,
                'avg_js_count' => 0,
                'by_source' => array(),
                'top_assets' => array(),
                'frequent_assets' => array()
            );
        }

        // Basic stats using recent timestamp filter (MySQL compatible approach)
        $basic_stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_assets,
                COUNT(CASE WHEN a.type = 'css' THEN 1 END) as total_css,
                COUNT(CASE WHEN a.type = 'js' THEN 1 END) as total_js,
                COUNT(DISTINCT a.profile_id) as profiles_with_assets,
                AVG(a.size) as avg_size
             FROM {$this->assets_table_name} a
             INNER JOIN {$this->table_name} p ON a.profile_id = p.id
             WHERE p.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY p.timestamp DESC",
            ARRAY_A
        );

        // Assets by source using recent timestamp filter
        $by_source = $wpdb->get_results(
            "SELECT
                a.source,
                a.source_name,
                a.type,
                COUNT(*) as count,
                AVG(a.size) as avg_size
             FROM {$this->assets_table_name} a
             INNER JOIN {$this->table_name} p ON a.profile_id = p.id
             WHERE p.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY a.source, a.source_name, a.type
             ORDER BY count DESC",
            ARRAY_A
        );

        // Top assets by size using recent timestamp filter
        $top_assets = $wpdb->get_results(
            "SELECT
                a.handle,
                a.src,
                a.type,
                a.source,
                a.source_name,
                AVG(a.size) as avg_size,
                COUNT(*) as frequency
             FROM {$this->assets_table_name} a
             INNER JOIN {$this->table_name} p ON a.profile_id = p.id
             WHERE a.size IS NOT NULL
             AND p.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY a.handle, a.src, a.type, a.source, a.source_name
             ORDER BY avg_size DESC
             LIMIT 20",
            ARRAY_A
        );

        return array(
            'total_assets' => (int) (($basic_stats['total_assets'] ?? 0)),
            'avg_css_count' => ($basic_stats && $basic_stats['profiles_with_assets'] > 0) ?
                round($basic_stats['total_css'] / $basic_stats['profiles_with_assets'], 2) : 0,
            'avg_js_count' => ($basic_stats && $basic_stats['profiles_with_assets'] > 0) ?
                round($basic_stats['total_js'] / $basic_stats['profiles_with_assets'], 2) : 0,
            'avg_size' => ($basic_stats && $basic_stats['avg_size']) ? round($basic_stats['avg_size']) : 0,
            'by_source' => $by_source ?: array(),
            'top_assets' => $top_assets ?: array()
        );
    }

    /**
     * Get latest profile data
     */
    public function get_latest_profile() {
        global $wpdb;

        $result = $wpdb->get_row(
            "SELECT * FROM {$this->table_name}
             ORDER BY timestamp DESC
             LIMIT 1",
            ARRAY_A
        );

        if ($result) {
            $result['plugins'] = json_decode($result['plugin_data'], true);
            unset($result['plugin_data']);
        }

        return $result;
    }

    /**
     * Get aggregated profile data
     */
    public function get_aggregated_data($limit = 100) {
        global $wpdb;

        // Get recent profiles for aggregation
        $profiles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 ORDER BY timestamp DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        if (empty($profiles)) {
            return array(
                'total_profiles' => 0,
                'avg_load_time' => 0,
                'avg_memory_usage' => 0,
                'plugins' => array(),
                'sample_rate' => $this->get_sample_rate() // Get actual configured rate
            );
        }

        $total_profiles = count($profiles);
        $total_load_time = 0;
        $total_memory = 0;
        $plugin_stats = array();

        foreach ($profiles as $profile) {
            $total_load_time += $profile['total_time'];
            $total_memory += $profile['total_memory'];

            $plugins = json_decode($profile['plugin_data'], true);
            if (!empty($plugins)) {
                foreach ($plugins as $plugin_name => $plugin_data) {
                    if (!isset($plugin_stats[$plugin_name])) {
                        $plugin_stats[$plugin_name] = array(
                            'name' => $plugin_data['name'],
                            'total_time' => 0,
                            'total_memory' => 0,
                            'total_files' => 0,
                            'profile_count' => 0,
                            'avg_time' => 0,
                            'avg_memory' => 0,
                            'avg_files' => 0
                        );
                    }

                    $plugin_stats[$plugin_name]['total_time'] += $plugin_data['load_time'];
                    $plugin_stats[$plugin_name]['total_memory'] += $plugin_data['memory_usage'];
                    $plugin_stats[$plugin_name]['total_files'] += $plugin_data['files_loaded'] ?? 1;
                    $plugin_stats[$plugin_name]['profile_count']++;
                }
            }
        }

        // Calculate averages for plugins
        foreach ($plugin_stats as $plugin_name => &$stats) {
            $stats['avg_time'] = $stats['total_time'] / $stats['profile_count'];
            $stats['avg_memory'] = $stats['total_memory'] / $stats['profile_count'];
            $stats['avg_files'] = $stats['total_files'] / $stats['profile_count'];
        }

        // Sort plugins by average load time (descending)
        uasort($plugin_stats, function($a, $b) {
            return $b['avg_time'] <=> $a['avg_time'];
        });

        return array(
            'total_profiles' => $total_profiles,
            'avg_load_time' => $total_load_time / $total_profiles,
            'avg_memory_usage' => $total_memory / $total_profiles,
            'plugins' => $plugin_stats,
            'sample_rate' => $this->get_sample_rate()
        );
    }

    /**
     * Get profile statistics
     */
    public function get_statistics() {
        global $wpdb;

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_profiles,
                AVG(total_time) as avg_load_time,
                AVG(total_memory) as avg_memory,
                MIN(timestamp) as oldest_profile,
                MAX(timestamp) as latest_profile
             FROM {$this->table_name}",
            ARRAY_A
        );

        return $stats;
    }

    /**
     * Clear old profile data
     */
    public function clear_old_data($days = 30) {
        global $wpdb;

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name}
                 WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        return $result;
    }

    /**
     * Clear all profile data
     */
    public function clear_all_data() {
        global $wpdb;

        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");

        // Also clear query and asset data
        $this->clear_all_query_data();
        $this->clear_all_assets_data();

        return $result !== false;
    }

    /**
     * Get table size information
     */
    public function get_table_info() {
        global $wpdb;

        $info = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as row_count,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                 FROM information_schema.TABLES
                 WHERE table_schema = %s
                 AND table_name = %s",
                DB_NAME,
                $this->table_name
            ),
            ARRAY_A
        );

        return $info;
    }

    /**
     * Check if table exists
     */
    public function table_exists() {
        global $wpdb;

        $table_name = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $this->table_name
            )
        );

        return $table_name === $this->table_name;
    }

    /**
     * Get current sample rate
     */
    private function get_sample_rate() {
        $options = get_option('instapulse_options', array());
        return isset($options['sample_rate']) ? (int) $options['sample_rate'] : 2;
    }


    /**
     * Get slow queries data
     */
    public function get_slow_queries($limit = 100) {
        global $wpdb;

        // Check if table exists first
        if (!$this->queries_table_exists()) {
            return array();
        }

        $queries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.*, p.request_uri as profile_request_uri
                 FROM {$this->queries_table_name} q
                 LEFT JOIN {$this->table_name} p ON q.profile_id = p.id
                 ORDER BY q.execution_time DESC, q.timestamp DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $queries ?: array();
    }

    /**
     * Get aggregated slow query statistics
     */
    public function get_slow_query_stats() {
        global $wpdb;

        // Check if table exists first
        if (!$this->queries_table_exists()) {
            return array(
                'basic_stats' => array(),
                'frequent_queries' => array()
            );
        }

        // Get basic stats
        $basic_stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_slow_queries,
                AVG(execution_time) as avg_execution_time,
                MAX(execution_time) as max_execution_time,
                MIN(execution_time) as min_execution_time
             FROM {$this->queries_table_name}",
            ARRAY_A
        );

        // Get most frequent slow queries
        $frequent_queries = $wpdb->get_results(
            "SELECT
                LEFT(query_sql, 100) as query_preview,
                COUNT(*) as frequency,
                AVG(execution_time) as avg_time,
                MAX(execution_time) as max_time
             FROM {$this->queries_table_name}
             GROUP BY LEFT(MD5(query_sql), 16)
             ORDER BY frequency DESC, avg_time DESC
             LIMIT 10",
            ARRAY_A
        );

        return array(
            'basic_stats' => $basic_stats ?: array(),
            'frequent_queries' => $frequent_queries ?: array()
        );
    }

    /**
     * Clear old query data
     */
    public function clear_old_query_data($days = 30) {
        global $wpdb;

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->queries_table_name}
                 WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        return $result;
    }

    /**
     * Clear all query data
     */
    public function clear_all_query_data() {
        global $wpdb;

        $result = $wpdb->query("TRUNCATE TABLE {$this->queries_table_name}");

        return $result !== false;
    }

    /**
     * Check if queries table exists
     */
    public function queries_table_exists() {
        global $wpdb;

        $table_name = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $this->queries_table_name
            )
        );

        return $table_name === $this->queries_table_name;
    }

    /**
     * Get slow query threshold from settings
     */
    public function get_slow_query_threshold() {
        $options = get_option('instapulse_options', array());
        return isset($options['slow_query_threshold']) ? (int) $options['slow_query_threshold'] : 50;
    }

    /**
     * Get table name for external use
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /**
     * Get queries table name for external use
     */
    public function get_queries_table_name() {
        return $this->queries_table_name;
    }

    /**
     * Get assets table name for external use
     */
    public function get_assets_table_name() {
        return $this->assets_table_name;
    }

    /**
     * Check if assets table exists
     */
    public function assets_table_exists() {
        global $wpdb;

        $table_name = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $this->assets_table_name
            )
        );

        return $table_name === $this->assets_table_name;
    }

    /**
     * Clear all assets data
     */
    public function clear_all_assets_data() {
        global $wpdb;

        if (!$this->assets_table_exists()) {
            return true;
        }

        $result = $wpdb->query("TRUNCATE TABLE {$this->assets_table_name}");

        return $result !== false;
    }

    /**
     * Clear old assets data
     */
    public function clear_old_assets_data($days = 30) {
        global $wpdb;

        if (!$this->assets_table_exists()) {
            return true;
        }

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->assets_table_name}
                 WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        return $result;
    }
}