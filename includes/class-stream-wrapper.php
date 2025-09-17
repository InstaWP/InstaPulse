<?php
/**
 * InstaPulse Stream Wrapper
 *
 * Intercepts file operations to track plugin loading times
 *
 * @package InstaPulse
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stream wrapper to intercept file operations
 */
class InstaPulse_Stream_Wrapper {

    private static $profiler = null;
    private static $original_wrapper = 'file';
    private static $active = false;

    public $context;
    private $handle;
    private $file_path;
    private $start_time;
    private $start_memory;
    private $wrapper_overhead_time = 0;

    /**
     * Initialize the stream wrapper
     */
    public static function init($profiler_instance) {
        if (self::$active) {
            return;
        }

        self::$profiler = $profiler_instance;

        // Only intercept if we have the required functions
        if (!function_exists('stream_wrapper_unregister') ||
            !function_exists('stream_wrapper_register') ||
            !function_exists('stream_wrapper_restore')) {
            return;
        }

        // Backup original file wrapper and register our custom one
        if (stream_wrapper_unregister('file')) {
            if (stream_wrapper_register('file', __CLASS__)) {
                self::$active = true;
            } else {
                // Restore original if registration failed
                stream_wrapper_restore('file');
            }
        }
    }

    /**
     * Restore the original file wrapper
     */
    public static function restore() {
        if (self::$active) {
            stream_wrapper_restore('file');
            self::$active = false;
        }
    }

    /**
     * Stream wrapper: stream_open
     */
    public function stream_open($path, $mode, $options, &$opened_path) {
        $this->file_path = $path;

        // Only track if this is a trackable file to avoid infinite recursion
        $should_track = $this->is_trackable_file($path);

        if ($should_track) {
            $this->start_time = microtime(true);
            $this->start_memory = memory_get_usage(true);
        }

        // Define STREAM_USE_INCLUDE_PATH if not defined (value is 1)
        if (!defined('STREAM_USE_INCLUDE_PATH')) {
            define('STREAM_USE_INCLUDE_PATH', 1);
        }

        // Temporarily restore original wrapper to avoid recursion (essential for functionality)
        $original_restored = false;
        if (self::$active) {
            stream_wrapper_restore('file');
            $original_restored = true;
        }

        // Use the original file handler
        $this->handle = @fopen($path, $mode, ($options & STREAM_USE_INCLUDE_PATH) ? true : false, $this->context);

        // Re-register our wrapper
        if ($original_restored) {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', __CLASS__);
        }

        // Only track on close for simplicity - this captures the full file operation

        return $this->handle !== false;
    }

    /**
     * Stream wrapper: stream_close
     */
    public function stream_close() {
        if ($this->handle && $this->is_trackable_file($this->file_path)) {
            $this->track_file_operation('close');
        }

        if ($this->handle && is_resource($this->handle)) {
            $result = fclose($this->handle);
            $this->handle = null;
            return $result;
        }
        return true;
    }

    /**
     * Stream wrapper: stream_read
     */
    public function stream_read($count) {
        if (!$this->handle) {
            return false;
        }
        return fread($this->handle, $count);
    }

    /**
     * Stream wrapper: stream_write
     */
    public function stream_write($data) {
        if (!$this->handle) {
            return 0;
        }
        return fwrite($this->handle, $data);
    }

    /**
     * Stream wrapper: stream_tell
     */
    public function stream_tell() {
        if (!$this->handle) {
            return false;
        }
        return ftell($this->handle);
    }

    /**
     * Stream wrapper: stream_eof
     */
    public function stream_eof() {
        if (!$this->handle) {
            return true;
        }
        return feof($this->handle);
    }

    /**
     * Stream wrapper: stream_seek
     */
    public function stream_seek($offset, $whence = SEEK_SET) {
        if (!$this->handle) {
            return false;
        }
        return fseek($this->handle, $offset, $whence) === 0;
    }

    /**
     * Stream wrapper: stream_stat
     */
    public function stream_stat() {
        if (!$this->handle) {
            return false;
        }
        return fstat($this->handle);
    }

    /**
     * Stream wrapper: url_stat
     */
    public function url_stat($path, $flags) {
        // Temporarily restore original wrapper to avoid recursion
        $original_restored = false;
        if (self::$active) {
            stream_wrapper_restore('file');
            $original_restored = true;
        }

        // Use original stat function
        $result = @stat($path);

        // Re-register our wrapper
        if ($original_restored) {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', __CLASS__);
        }

        return $result;
    }

    /**
     * Check if file is trackable (plugin or theme file)
     */
    private function is_trackable_file($path) {
        // Skip if path is empty or not a string
        if (empty($path) || !is_string($path)) {
            return false;
        }

        // Skip temporary files and common WordPress files that may not exist
        $skip_files = array('.maintenance', '.htaccess', 'wp-config.php');
        $basename = basename($path);
        if (in_array($basename, $skip_files)) {
            return false;
        }

        // Don't track WordPress core files to avoid interference
        if (defined('ABSPATH')) {
            $wp_includes_dir = ABSPATH . 'wp-includes';
            $wp_admin_dir = ABSPATH . 'wp-admin';

            if (strpos($path, $wp_includes_dir) === 0 || strpos($path, $wp_admin_dir) === 0) {
                return false;
            }
        }

        // Track plugin files (only .php files)
        if (defined('WP_PLUGIN_DIR') && strpos($path, WP_PLUGIN_DIR) === 0 && substr($path, -4) === '.php') {
            return true;
        }

        // Track theme files (only .php files)
        if (defined('WP_CONTENT_DIR')) {
            $themes_dir = WP_CONTENT_DIR . '/themes';
            if (strpos($path, $themes_dir) === 0 && substr($path, -4) === '.php') {
                return true;
            }
        }

        // Track MU plugin files (only .php files)
        if (defined('WPMU_PLUGIN_DIR') && strpos($path, WPMU_PLUGIN_DIR) === 0 && substr($path, -4) === '.php') {
            return true;
        }

        return false;
    }

    /**
     * Track file operation
     */
    private function track_file_operation($operation) {
        if (!self::$profiler || !method_exists(self::$profiler, 'record_plugin_load')) {
            return;
        }

        if ($operation === 'close') {
            $end_time = microtime(true);
            $end_memory = memory_get_usage(true);
            $peak_memory = memory_get_peak_usage(true);

            $load_time = ($end_time - $this->start_time) * 1000; // Convert to milliseconds

            // Use peak memory difference as it's more accurate for file loading
            $memory_used_peak = max(0, $peak_memory - $this->start_memory);
            $memory_used_current = max(0, $end_memory - $this->start_memory);

            // Use the larger of current or peak memory difference
            $memory_used = max($memory_used_current, $memory_used_peak);

            // Lower threshold from 0.5ms to 0.1ms to capture more operations
            // This will give more accurate total timing without being overly granular
            if ($load_time > 0.1 || $memory_used > 1024) { // 0.1ms or 1KB
                self::$profiler->record_plugin_load($this->file_path, $load_time, $memory_used);
            }
        }
    }

    /**
     * Stream wrapper: stream_flush
     */
    public function stream_flush() {
        if (!$this->handle) {
            return false;
        }
        return fflush($this->handle);
    }

    /**
     * Stream wrapper: stream_lock
     */
    public function stream_lock($operation) {
        if (!$this->handle) {
            return false;
        }
        return flock($this->handle, $operation);
    }

    /**
     * Stream wrapper: stream_metadata
     */
    public function stream_metadata($path, $option, $value) {
        // Temporarily restore original wrapper to avoid recursion
        $original_restored = false;
        if (self::$active) {
            stream_wrapper_restore('file');
            $original_restored = true;
        }

        $result = false;
        switch ($option) {
            case STREAM_META_TOUCH:
                $result = touch($path, $value[0] ?? time(), $value[1] ?? time());
                break;
            case STREAM_META_OWNER_NAME:
            case STREAM_META_OWNER:
                $result = chown($path, $value);
                break;
            case STREAM_META_GROUP_NAME:
            case STREAM_META_GROUP:
                $result = chgrp($path, $value);
                break;
            case STREAM_META_ACCESS:
                $result = chmod($path, $value);
                break;
        }

        // Re-register our wrapper
        if ($original_restored) {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', __CLASS__);
        }

        return $result;
    }

    /**
     * Stream wrapper: unlink
     */
    public function unlink($path) {
        // Temporarily restore original wrapper to avoid recursion
        $original_restored = false;
        if (self::$active) {
            stream_wrapper_restore('file');
            $original_restored = true;
        }

        $result = unlink($path);

        // Re-register our wrapper
        if ($original_restored) {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', __CLASS__);
        }

        return $result;
    }

    /**
     * Stream wrapper: rename
     */
    public function rename($path_from, $path_to) {
        // Temporarily restore original wrapper to avoid recursion
        $original_restored = false;
        if (self::$active) {
            stream_wrapper_restore('file');
            $original_restored = true;
        }

        $result = rename($path_from, $path_to);

        // Re-register our wrapper
        if ($original_restored) {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', __CLASS__);
        }

        return $result;
    }

    /**
     * Stream wrapper: mkdir
     */
    public function mkdir($path, $mode, $options) {
        // Temporarily restore original wrapper to avoid recursion
        $original_restored = false;
        if (self::$active) {
            stream_wrapper_restore('file');
            $original_restored = true;
        }

        $result = mkdir($path, $mode, ($options & STREAM_MKDIR_RECURSIVE) ? true : false);

        // Re-register our wrapper
        if ($original_restored) {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', __CLASS__);
        }

        return $result;
    }

    /**
     * Stream wrapper: rmdir
     */
    public function rmdir($path) {
        // Temporarily restore original wrapper to avoid recursion
        $original_restored = false;
        if (self::$active) {
            stream_wrapper_restore('file');
            $original_restored = true;
        }

        $result = rmdir($path);

        // Re-register our wrapper
        if ($original_restored) {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', __CLASS__);
        }

        return $result;
    }

    /**
     * Stream wrapper: dir_opendir
     */
    public function dir_opendir($path, $options) {
        // Temporarily restore original wrapper to avoid recursion
        $original_restored = false;
        if (self::$active) {
            stream_wrapper_restore('file');
            $original_restored = true;
        }

        $this->handle = opendir($path);

        // Re-register our wrapper
        if ($original_restored) {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', __CLASS__);
        }

        return $this->handle !== false;
    }

    /**
     * Stream wrapper: dir_readdir
     */
    public function dir_readdir() {
        if (!$this->handle) {
            return false;
        }
        return readdir($this->handle);
    }

    /**
     * Stream wrapper: dir_rewinddir
     */
    public function dir_rewinddir() {
        if (!$this->handle) {
            return false;
        }
        return rewinddir($this->handle);
    }

    /**
     * Stream wrapper: dir_closedir
     */
    public function dir_closedir() {
        if ($this->handle) {
            return closedir($this->handle);
        }
        return true;
    }

    /**
     * Stream wrapper: stream_cast
     */
    public function stream_cast($cast_as) {
        return $this->handle;
    }

    /**
     * Stream wrapper: stream_set_option
     */
    public function stream_set_option($option, $arg1, $arg2) {
        return false;
    }

    /**
     * Destructor - ensure resources are cleaned up
     */
    public function __destruct() {
        if ($this->handle && is_resource($this->handle)) {
            @fclose($this->handle);
        }
    }
}