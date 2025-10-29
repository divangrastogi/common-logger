<?php
/**
 * Common Logger Core Class
 *
 * Handles the core logging functionality for the Common Logger plugin.
 *
 * @package CommonLogger
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Common Logger Core Class
 */
final class Common_Logger {

    const OPTION_STORAGE_MODE            = 'common_logger_storage_mode';
    const OPTION_DB_VERSION              = 'common_logger_db_version';
    const OPTION_HOOK_TRACING_ENABLED    = 'common_logger_hook_tracing_enabled';
    const OPTION_HOOK_PREFIX             = 'common_logger_hook_prefix';
    const OPTION_PHP_ERROR_ENABLED       = 'common_logger_php_error_enabled';
    const OPTION_SLOW_QUERY_ENABLED      = 'common_logger_slow_query_enabled';
    const OPTION_SLOW_QUERY_THRESHOLD    = 'common_logger_slow_query_threshold';
    const OPTION_ERROR_ONLY_MODE         = 'common_logger_error_only_mode';
    const OPTION_AI_INSIGHTS_ENABLED     = 'common_logger_enable_ai_insights';
    const OPTION_TOOL_INTEGRATIONS       = 'common_logger_enable_tool_integrations';
    const OPTION_REST_API_ENABLED        = 'common_logger_enable_rest_api';
    const OPTION_DEVELOPER_MODE          = 'common_logger_developer_mode';
    const OPTION_NOTIFICATION_THRESHOLD  = 'common_logger_notification_threshold';
    const STORAGE_FILE                   = 'file';
    const STORAGE_DATABASE               = 'database';
    const DB_VERSION                     = '1.1.0'; // Updated for enhanced schema
    const DEFAULT_LOG_LIMIT              = 20;
    const DEFAULT_SLOW_QUERY_THRESHOLD   = 0.5;

    /**
     * Singleton instance
     *
     * @var Common_Logger|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Common_Logger
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Private constructor for singleton
    }

    /**
     * Register activation hook
     */
    public static function activate() {
        $instance = self::instance();
        $instance->maybe_create_storage_directory();
        $instance->create_database_table();
    }

    /**
     * Log a message with enhanced AI-powered analysis
      *
      * @param string $message Message to log.
      * @param string $level   Log level (INFO, WARNING, ERROR, DEBUG).
      * @param array  $context Additional context data.
      */
     public function log($message, $level = 'INFO', $context = array()) {
         $level = strtoupper($level);
         $timestamp = current_time('mysql');
         $context = is_array($context) ? common_logger_sanitize_context($context) : array();

         // Apply pre-log filter
         $should_log = apply_filters('common_logger_should_log', true, $context);
         if (!$should_log) {
             return;
         }

         // Apply pre-log processor
         $log_data = apply_filters('common_logger_pre_log', array(
             'message' => $message,
             'context' => $context
         ));
         $message = $log_data['message'];
         $context = $log_data['context'];

         // Prevent self-logging
         if (isset($context['_origin_file']) && strpos($context['_origin_file'], 'common-logger') !== false) {
             return;
         }
         if (isset($context['_origin_plugin']) && $context['_origin_plugin'] === 'common-logger') {
             return;
         }

         // Enhanced origin detection
         if (!isset($context['origin_metadata'])) {
             $context['origin_metadata'] = $this->detect_enhanced_origin_metadata();
         }

         // Build function chain if not provided
         if (!isset($context['function_chain']) && $this->is_ai_insights_enabled()) {
             $context['function_chain'] = common_logger_build_function_chain();
         }

         // Extract structured data for database storage
         $structured_data = $this->extract_structured_data($context);

         $context_string = !empty($context) ? wp_json_encode($context) : '';

         $log_id = null;
         if (self::STORAGE_DATABASE === $this->get_storage_mode()) {
             $log_id = $this->write_to_database_enhanced($timestamp, $level, $message, $context_string, $structured_data);
         } else {
             $this->write_to_file_enhanced($timestamp, $level, $message, $context_string, $structured_data);
         }

         // Apply post-log processor
         do_action('common_logger_post_log', $log_id, $level, array(
             'timestamp' => $timestamp,
             'message' => $message,
             'context' => $context,
             'structured_data' => $structured_data,
         ));

         if (defined('WP_DEBUG') && WP_DEBUG) {
             error_log('[Common Logger] ' . $message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
         }

         // Developer mode: output to screen if in admin
         if ($this->is_developer_mode() && is_admin()) {
             $this->developer_mode_output($level, $message, $context);
         }
     }
    /**
     * Convenience wrapper for info level logging.
     */
    public function info($message, $context = array()) {
        $this->log($message, 'INFO', $context);
    }

    /**
     * Convenience wrapper for warning level logging.
     */
    public function warning($message, $context = array()) {
        $this->log($message, 'WARNING', $context);
    }

    /**
     * Convenience wrapper for notice level logging.
     */
    public function notice($message, $context = array()) {
        $this->log($message, 'NOTICE', $context);
    }

    /**
     * Convenience wrapper for error level logging.
     */
    public function error($message, $context = array()) {
        $this->log($message, 'ERROR', $context);
    }

     /**
      * Convenience wrapper for debug level logging.
      */
     public function debug($message, $context = array()) {
         $this->log($message, 'DEBUG', $context);
     }

     /**
      * Check if AI insights are enabled
      *
      * @return bool
      */
     public function is_ai_insights_enabled() {
         return (bool) get_option(self::OPTION_AI_INSIGHTS_ENABLED, true);
     }

     /**
      * Check if developer mode is enabled
      *
      * @return bool
      */
     public function is_developer_mode() {
         return (bool) get_option(self::OPTION_DEVELOPER_MODE, false);
     }

     /**
      * Detect enhanced origin metadata
      *
      * @return array
      */
     private function detect_enhanced_origin_metadata() {
         $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
         $origin_file = '';
         $origin_line = 0;

         // Find the first non-common-logger file in the backtrace
         foreach ($backtrace as $frame) {
             if (isset($frame['file']) && strpos($frame['file'], 'common-logger') === false) {
                 $origin_file = $frame['file'];
                 $origin_line = isset($frame['line']) ? $frame['line'] : 0;
                 break;
             }
         }

         return common_logger_detect_enhanced_origin_metadata($origin_file, $origin_line);
     }

     /**
      * Extract structured data from context for database storage
      *
      * @param array $context Log context
      * @return array
      */
     private function extract_structured_data($context) {
         $data = array(
             'plugin' => '',
             'theme' => '',
             'file' => '',
             'line' => 0,
             'hook' => '',
             'function_chain' => array(),
         );

         if (isset($context['origin_metadata'])) {
             $metadata = $context['origin_metadata'];
             $data['plugin'] = isset($metadata['plugin']) ? $metadata['plugin'] : '';
             $data['theme'] = isset($metadata['theme']) ? $metadata['theme'] : '';
             $data['file'] = isset($metadata['file']) ? $metadata['file'] : '';
             $data['line'] = isset($metadata['line']) ? intval($metadata['line']) : 0;
             $data['hook'] = isset($metadata['hook']) ? $metadata['hook'] : '';
         }

         if (isset($context['function_chain']) && is_array($context['function_chain'])) {
             $data['function_chain'] = $context['function_chain'];
         }

         return $data;
     }

     /**
      * Enhanced database write with structured data
      *
      * @param string $timestamp Timestamp
      * @param string $level Log level
      * @param string $message Log message
      * @param string $context_string JSON context
      * @param array $structured_data Structured data
      * @return int|null Log ID
      */
     private function write_to_database_enhanced($timestamp, $level, $message, $context_string, $structured_data) {
         global $wpdb;

         $table = $this->get_table_name();

         // Check if enhanced columns exist, fallback to basic insert if not
         $columns_exist = $this->check_enhanced_columns_exist();

         if ($columns_exist) {
             $result = $wpdb->insert(
                 $table,
                 array(
                     'timestamp' => $timestamp,
                     'level' => $level,
                     'message' => $message,
                     'context' => $context_string,
                     'plugin' => $structured_data['plugin'],
                     'theme' => $structured_data['theme'],
                     'file' => $structured_data['file'],
                     'line' => $structured_data['line'],
                     'hook' => $structured_data['hook'],
                     'function_chain' => wp_json_encode($structured_data['function_chain']),
                 ),
                 array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
             );
         } else {
             // Fallback to basic insert for backward compatibility
             $result = $wpdb->insert(
                 $table,
                 array(
                     'timestamp' => $timestamp,
                     'level' => $level,
                     'message' => $message,
                     'context' => $context_string,
                 ),
                 array('%s', '%s', '%s', '%s')
             );
         }

         return $result ? $wpdb->insert_id : null;
     }

     /**
      * Check if enhanced columns exist in the database table
      *
      * @return bool
      */
     private function check_enhanced_columns_exist() {
         global $wpdb;

         $table = $this->get_table_name();
         $enhanced_columns = array('plugin', 'theme', 'file', 'line', 'hook', 'function_chain');

         foreach ($enhanced_columns as $column) {
             $exists = $wpdb->get_var($wpdb->prepare(
                 "SHOW COLUMNS FROM {$table} LIKE %s",
                 $column
             ));

             if (!$exists) {
                 return false;
             }
         }

         return true;
     }

     /**
      * Enhanced file write with structured data
      *
      * @param string $timestamp Timestamp
      * @param string $level Log level
      * @param string $message Log message
      * @param string $context_string JSON context
      * @param array $structured_data Structured data
      */
     private function write_to_file_enhanced($timestamp, $level, $message, $context_string, $structured_data) {
         $log_entry = array(
             'timestamp' => $timestamp,
             'level' => $level,
             'message' => $message,
         );

         // Add enhanced data
         if (!empty($structured_data['plugin'])) {
             $log_entry['plugin'] = $structured_data['plugin'];
         }
         if (!empty($structured_data['theme'])) {
             $log_entry['theme'] = $structured_data['theme'];
         }
         if (!empty($structured_data['file'])) {
             $log_entry['file'] = $structured_data['file'];
         }
         if (!empty($structured_data['line'])) {
             $log_entry['line'] = $structured_data['line'];
         }
         if (!empty($structured_data['hook'])) {
             $log_entry['hook'] = $structured_data['hook'];
         }
         if (!empty($structured_data['function_chain'])) {
             $log_entry['function_chain'] = $structured_data['function_chain'];
         }

         // Add context if available
         if (!empty($context_string)) {
             $context_array = json_decode($context_string, true);
             if (is_array($context_array)) {
                 $log_entry['context'] = $context_array;
             }
         }

         $log_line = wp_json_encode($log_entry) . PHP_EOL;

         $file_path = $this->get_log_file_path();
         $this->maybe_create_storage_directory();

         file_put_contents($file_path, $log_line, FILE_APPEND | LOCK_EX);
     }

     /**
      * Developer mode output
      *
      * @param string $level Log level
      * @param string $message Log message
      * @param array $context Log context
      */
     private function developer_mode_output($level, $message, $context) {
         $colors = array(
             'ERROR' => '#ff4444',
             'WARNING' => '#ffaa00',
             'NOTICE' => '#00aaff',
             'INFO' => '#00aa00',
             'DEBUG' => '#aaaaaa',
         );

         $color = isset($colors[$level]) ? $colors[$level] : '#000000';

         echo '<div style="background:#f9f9f9;border-left:4px solid ' . esc_attr($color) . ';padding:10px;margin:5px 0;font-family:monospace;font-size:12px;">';
         echo '<strong style="color:' . esc_attr($color) . ';">[' . esc_html($level) . ']</strong> ';
         echo esc_html($message);

         if (!empty($context['function_chain'])) {
             echo '<br><small><strong>Chain:</strong> ' . esc_html(implode(' → ', $context['function_chain'])) . '</small>';
         }

         echo '</div>';
     }

    /**
     * Get the current storage mode option.
     *
     * @return string
     */
    public function get_storage_mode() {
        $mode = get_option(self::OPTION_STORAGE_MODE, self::STORAGE_FILE);

        return in_array($mode, array(self::STORAGE_FILE, self::STORAGE_DATABASE), true) ? $mode : self::STORAGE_FILE;
    }

    /**
     * Get logs limited by the provided arguments.
     *
     * @param array $args {
     *     @type int $limit Number of log entries to fetch. Default 200.
     * }
     *
     * @return array
     */
    public function get_logs($args = array()) {
        $args = wp_parse_args(
            $args,
            array(
                'limit' => self::DEFAULT_LOG_LIMIT,
                'level' => '',
                'plugin' => '',
                'search' => '',
                'fetch_limit' => 0,
                'offset' => 0,
            )
        );

        $limit = max(1, absint($args['limit']));
        $fetch_limit = max($limit, absint($args['fetch_limit']));
        $offset = max(0, absint($args['offset']));

        if (!$fetch_limit) {
            $fetch_limit = max($limit * 4, self::DEFAULT_LOG_LIMIT * 2);
        }

        $is_filtered = !empty($args['level']) || !empty($args['plugin']) || !empty($args['search']);

        if (self::STORAGE_DATABASE === $this->get_storage_mode()) {
            $logs = $this->get_logs_from_database($fetch_limit, $offset);
        } else {
            // For file storage, read the entire file if filtering is active to ensure accuracy.
            $file_limit = $is_filtered ? PHP_INT_MAX : $fetch_limit;
            $logs = $this->get_logs_from_file($file_limit);
        }

        $normalized = array_map(array($this, 'normalize_log_entry'), $logs);

        $level_filter = strtoupper((string) $args['level']);
        $plugin_filter = strtolower((string) $args['plugin']);
        $search_filter = (string) $args['search'];

        $filtered = array();

        foreach ($normalized as $entry) {
            if ($level_filter && strtoupper($entry['level']) !== $level_filter) {
                continue;
            }

            if ($plugin_filter && strtolower((string) $entry['origin_plugin']) !== $plugin_filter) {
                continue;
            }

            if ($search_filter) {
                $haystack = array(
                    $entry['message'],
                    $entry['issue_summary'],
                    $entry['origin_file'],
                );

                if (!empty($entry['context_array'])) {
                    $haystack[] = wp_json_encode($entry['context_array']);
                }

                $haystack_string = implode(' ', array_filter(array_map('strval', $haystack)));

                if (false === stripos($haystack_string, $search_filter)) {
                    continue;
                }
            }

            $filtered[] = $entry;
        }

        return array_slice($filtered, 0, $limit);
    }

    /**
     * Get the total count of logs matching the provided filters.
     *
     * @param array $args {
     *     @type string $level Filter by log level.
     *     @type string $plugin Filter by plugin.
     *     @type string $search Search in message and context.
     * }
     *
     * @return int
     */
    public function get_logs_count($args = array()) {
        $args = wp_parse_args($args, array('level' => '', 'plugin' => '', 'search' => ''));

        if (self::STORAGE_DATABASE === $this->get_storage_mode()) {
            return $this->get_logs_count_from_database($args);
        } else {
            // For file storage, read all logs and count the filtered results.
            // This is inefficient but necessary for accurate counting with filters.
            $args['limit'] = PHP_INT_MAX;
            $args['fetch_limit'] = PHP_INT_MAX;
            $filtered_logs = $this->get_logs($args);

            return count($filtered_logs);
        }
    }

    /**
     * Get logs count from database with filters.
     */
    private function get_logs_count_from_database($args) {
        global $wpdb;

        $table = $this->get_table_name();
        $where_parts = array();

        $level_filter = strtoupper((string) $args['level']);
        if ($level_filter) {
            $where_parts[] = $wpdb->prepare('level = %s', $level_filter);
        }

         $plugin_filter = strtolower((string) $args['plugin']);
         if ($plugin_filter) {
             $where_parts[] = $wpdb->prepare('plugin = %s', $plugin_filter);
         }

        $search_filter = (string) $args['search'];
        if ($search_filter) {
            $escaped_search = $wpdb->esc_like($search_filter);
            $where_parts[] = $wpdb->prepare('(message LIKE %s OR context LIKE %s)', '%' . $escaped_search . '%', '%' . $escaped_search . '%');
        }

        $where_clause = empty($where_parts) ? '' : ' WHERE ' . implode(' AND ', $where_parts);

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}{$where_clause}");
        return (int) $count;
    }

    /**
     * Clear logs based on the storage mode.
     */
    public function clear_logs() {
        if (self::STORAGE_DATABASE === $this->get_storage_mode()) {
            $this->clear_database_logs();
        } else {
            $this->clear_file_logs();
        }
    }

    /**
     * Get log file path.
     *
     * @return string
     */
    public function get_log_file_path() {
        $uploads = wp_upload_dir();

        return trailingslashit($uploads['basedir']) . 'common-logger-logs/common.log';
    }

    /**
     * Get log file URL.
     *
     * @return string|null
     */
    public function get_log_file_url() {
        $uploads = wp_upload_dir();
        $path = $this->get_log_file_path();

        if (file_exists($path)) {
            return trailingslashit($uploads['baseurl']) . 'common-logger-logs/common.log';
        }

        return null;
    }

    /**
     * Determine if hook tracing is enabled.
     *
     * @return bool
     */
    public function is_hook_tracing_enabled() {
        return (bool) get_option(self::OPTION_HOOK_TRACING_ENABLED, false);
    }

    /**
     * Retrieve configured hook prefix for tracing.
     *
     * @return string
     */
    public function get_hook_prefix() {
        $prefix = (string) get_option(self::OPTION_HOOK_PREFIX, '');

        return trim($prefix);
    }

    /**
     * Determine if PHP error capture is enabled.
     *
     * @return bool
     */
    public function is_php_error_logging_enabled() {
        return (bool) get_option(self::OPTION_PHP_ERROR_ENABLED, false);
    }

    /**
     * Determine if slow query capture is enabled.
     *
     * @return bool
     */
    public function is_slow_query_logging_enabled() {
        return (bool) get_option(self::OPTION_SLOW_QUERY_ENABLED, false);
    }

    /**
     * Determine if error-only mode is enabled.
     *
     * @return bool
     */
    public function is_error_only_mode() {
        return (bool) get_option(self::OPTION_ERROR_ONLY_MODE, false);
    }

    /**
     * Get slow query threshold in seconds.
     *
     * @return float
     */
    public function get_slow_query_threshold() {
        $value = (float) get_option(self::OPTION_SLOW_QUERY_THRESHOLD, self::DEFAULT_SLOW_QUERY_THRESHOLD);

        return $value > 0 ? $value : self::DEFAULT_SLOW_QUERY_THRESHOLD;
    }

    /**
     * Ensure log directory exists.
     */
    private function maybe_create_storage_directory() {
        $uploads = wp_upload_dir();
        $dir = trailingslashit($uploads['basedir']) . 'common-logger-logs';

        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }

    /**
     * Write a log entry to the filesystem.
     */
    private function write_to_file($timestamp, $level, $message, $context_string) {
        $this->maybe_create_storage_directory();

        $entry = sprintf('[%s] [%s] %s', $timestamp, $level, $message);

        if (!empty($context_string)) {
            $entry .= ' | Context: ' . $context_string;
        }

        $entry .= PHP_EOL;

        file_put_contents($this->get_log_file_path(), $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Persist a log entry into the database.
     */
    private function write_to_database($timestamp, $level, $message, $context_string) {
        global $wpdb;

        $this->create_database_table();

        $wpdb->insert(
            $this->get_table_name(),
            array(
                'logged_at' => $timestamp,
                'level' => $level,
                'message' => $message,
                'context' => $context_string,
            ),
            array('%s', '%s', '%s', '%s')
        );
    }

    /**
     * Retrieve logs from the database.
     *
     * @param int $limit Number of entries to fetch.
     *
     * @return array
     */
    private function get_logs_from_database($limit, $offset = 0) {
        global $wpdb;

        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $table = $this->get_table_name();

        if ($offset > 0) {
            $results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset),
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit),
                ARRAY_A
            );
        }

        return is_array($results) ? $results : array();
    }

    /**
     * Retrieve logs from the file system.
     *
     * @param int $limit Number of entries.
     *
     * @return array
     */
    private function get_logs_from_file($limit) {
        $path = $this->get_log_file_path();

        if (!file_exists($path)) {
            return array();
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (!is_array($lines)) {
            return array();
        }

        $lines = array_slice($lines, -absint($limit));
        $lines = array_reverse($lines);

        $entries = array();

        foreach ($lines as $line) {
            $entries[] = $this->parse_file_log_line($line);
        }

        return $entries;
    }

    /**
     * Normalize log entry structure.
     */
    private function normalize_log_entry($entry) {
        $logged_at = isset($entry['logged_at']) ? $entry['logged_at'] : '';
        $level = isset($entry['level']) ? $entry['level'] : '';
        $message = isset($entry['message']) ? $entry['message'] : '';
        $context = isset($entry['context']) ? $entry['context'] : '';

        $context_array = array();

        if (is_array($context)) {
            $context_array = $context;
        } elseif (is_string($context) && '' !== $context) {
            $decoded = json_decode($context, true);

            if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
                $context_array = $decoded;
            }
        }

        $origin_plugin = isset($context_array['_origin_plugin']) ? $context_array['_origin_plugin'] : '';
        $origin_file = isset($context_array['_origin_file']) ? $context_array['_origin_file'] : '';

        $issue_summary = $this->summarize_issue_from_context($level, $context_array);

        return array(
            'id' => isset($entry['id']) ? (int) $entry['id'] : 0,
            'logged_at' => $logged_at,
            'level' => $level,
            'message' => $message,
            'context' => is_string($context) ? $context : wp_json_encode($context_array),
            'context_array' => $context_array,
            'origin_plugin' => $origin_plugin,
            'origin_file' => $origin_file,
            'issue_summary' => $issue_summary,
        );
    }

    /**
     * Parse a log line originating from file storage.
     */
    private function parse_file_log_line($line) {
        $entry = json_decode($line, true);

        if (JSON_ERROR_NONE !== json_last_error() || !is_array($entry)) {
            // Fallback for old log format or invalid JSON
            $matches = array();
            if (preg_match('/^\[(.*?)\]\s*\[(.*?)\]\s*(.*?)(?:\s*\|\s*Context:\s*(.*))?$/', $line, $matches)) {
                return array(
                    'logged_at' => $matches[1],
                    'level' => $matches[2],
                    'message' => $matches[3],
                    'context' => isset($matches[4]) ? $matches[4] : '',
                );
            }

            return array(
                'logged_at' => '',
                'level' => '',
                'message' => $line,
                'context' => '',
            );
        }

        // Normalize keys for compatibility with database structure
        return array(
            'logged_at' => isset($entry['timestamp']) ? $entry['timestamp'] : '',
            'level' => isset($entry['level']) ? $entry['level'] : '',
            'message' => isset($entry['message']) ? $entry['message'] : '',
            'context' => isset($entry['context']) ? wp_json_encode($entry['context']) : '',
            'plugin' => isset($entry['plugin']) ? $entry['plugin'] : '',
            'theme' => isset($entry['theme']) ? $entry['theme'] : '',
            'file' => isset($entry['file']) ? $entry['file'] : '',
            'line' => isset($entry['line']) ? $entry['line'] : 0,
            'hook' => isset($entry['hook']) ? $entry['hook'] : '',
            'function_chain' => isset($entry['function_chain']) ? wp_json_encode($entry['function_chain']) : '',
        );
    }

    /**
     * Derive a short issue description from context.
     */
    private function summarize_issue_from_context($level, $context) {
        if (empty($context) || !is_array($context)) {
            return '';
        }

        if (isset($context['error']) && isset($context['file']) && isset($context['line'])) {
            return sprintf('%s in %s:%s', $context['error'], $context['file'], $context['line']);
        }

        if (isset($context['message'])) {
            return (string) $context['message'];
        }

        if (isset($context['sql']) && isset($context['time'])) {
            return sprintf('Slow query (%.3fs)', (float) $context['time']);
        }

        if (isset($context['hook'])) {
            $summary = sprintf('Hook: %s', $context['hook']);
            if (isset($context['process_stop']) && $context['process_stop']) {
                $summary .= ' (PROCESS STOPPED - no callbacks)';
            } elseif (isset($context['action_count']) || isset($context['filter_count'])) {
                $counts = array();
                if (isset($context['action_count']) && $context['action_count'] > 0) {
                    $counts[] = $context['action_count'] . ' actions';
                }
                if (isset($context['filter_count']) && $context['filter_count'] > 0) {
                    $counts[] = $context['filter_count'] . ' filters';
                }
                if (!empty($counts)) {
                    $summary .= ' (' . implode(', ', $counts) . ')';
                }
            }
            return $summary;
        }

        return '';
    }

    /**
     * Clear database logs.
     */
    private function clear_database_logs() {
        global $wpdb;

        $table = $this->get_table_name();
        $wpdb->query("TRUNCATE TABLE {$table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Delete logs from database using custom filters.
     *
     * @param array $args Filter arguments.
     * @return int Rows deleted.
     */
    public function purge_database_logs($args = array()) {
        if (self::STORAGE_DATABASE !== $this->get_storage_mode()) {
            return 0;
        }

        global $wpdb;

        $defaults = array(
            'level'  => '',
            'plugin' => '',
            'search' => '',
        );

        if (function_exists('wp_parse_args')) {
            $args = wp_parse_args($args, $defaults);
        } else {
            $args = array_merge($defaults, is_array($args) ? $args : array());
        }

        $table        = $this->get_table_name();
        $where_parts  = array();

        if (!empty($args['level'])) {
            $where_parts[] = $wpdb->prepare('level = %s', strtoupper($args['level']));
        }

        if (!empty($args['plugin'])) {
            $where_parts[] = $wpdb->prepare('context LIKE %s', '%"_origin_plugin":"' . $wpdb->esc_like(strtolower($args['plugin'])) . '"%');
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_parts[] = $wpdb->prepare('(message LIKE %s OR context LIKE %s)', $search, $search);
        }

        $where_clause = empty($where_parts) ? '' : ' WHERE ' . implode(' AND ', $where_parts);

        $deleted = $wpdb->query("DELETE FROM {$table}{$where_clause}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

        return is_numeric($deleted) ? (int) $deleted : 0;
    }

    /**
     * Clear file logs.
     */
    private function clear_file_logs() {
        $path = $this->get_log_file_path();

        if (file_exists($path)) {
            file_put_contents($path, '');
        }
    }

    /**
     * Create or update database table as needed.
     */
    private function create_database_table() {
        global $wpdb;

        $installed_version = get_option(self::OPTION_DB_VERSION);

        if (self::DB_VERSION === $installed_version && $this->table_exists()) {
            return;
        }

        $table_name = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp DATETIME NOT NULL,
            level VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            plugin VARCHAR(100) NULL,
            theme VARCHAR(100) NULL,
            file TEXT NULL,
            line INT(11) NULL DEFAULT 0,
            hook VARCHAR(100) NULL,
            function_chain LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY level (level),
            KEY plugin (plugin),
            KEY theme (theme),
            KEY hook (hook)
        ) {$charset_collate};";

        dbDelta($sql);

        // Handle database migration for existing installations
        $this->migrate_database_schema();

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
    }

    /**
     * Migrate database schema for existing installations
     */
    private function migrate_database_schema() {
        global $wpdb;

        $table_name = $this->get_table_name();

        // Check if new columns exist, if not add them
        $columns_to_add = array(
            'plugin' => "ALTER TABLE {$table_name} ADD COLUMN plugin VARCHAR(100) NULL AFTER context",
            'theme' => "ALTER TABLE {$table_name} ADD COLUMN theme VARCHAR(100) NULL AFTER plugin",
            'file' => "ALTER TABLE {$table_name} ADD COLUMN file TEXT NULL AFTER theme",
            'line' => "ALTER TABLE {$table_name} ADD COLUMN line INT(11) NULL DEFAULT 0 AFTER file",
            'hook' => "ALTER TABLE {$table_name} ADD COLUMN hook VARCHAR(100) NULL AFTER line",
            'function_chain' => "ALTER TABLE {$table_name} ADD COLUMN function_chain LONGTEXT NULL AFTER hook",
        );

        foreach ($columns_to_add as $column => $sql) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM {$table_name} LIKE %s",
                $column
            ));

            if (empty($column_exists)) {
                $wpdb->query($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
            }
        }

        // Rename logged_at to timestamp if it exists
        $old_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'logged_at'");
        if (!empty($old_column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} CHANGE logged_at timestamp DATETIME NOT NULL"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        }

        // Add indexes for new columns
        $indexes_to_add = array(
            'plugin' => "ALTER TABLE {$table_name} ADD KEY plugin (plugin)",
            'theme' => "ALTER TABLE {$table_name} ADD KEY theme (theme)",
            'hook' => "ALTER TABLE {$table_name} ADD KEY hook (hook)",
        );

        foreach ($indexes_to_add as $index_name => $sql) {
            $index_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
                $index_name
            ));

            if (empty($index_exists)) {
                $wpdb->query($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
            }
        }
    }

    /**
     * Determine if the logs table exists.
     *
     * @return bool
     */
    private function table_exists() {
        global $wpdb;

        $table = $this->get_table_name();

        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Get the full table name for logs.
     *
     * @return string
     */
    private function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'common_logger_logs';
    }
}
