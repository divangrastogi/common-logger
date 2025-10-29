<?php
/**
 * Common Logger REST API Class
 *
 * Handles REST API endpoints for the Common Logger plugin.
 *
 * @package CommonLogger
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Common Logger REST API Class
 */
class Common_Logger_REST_API {

    /**
     * Singleton instance
     *
     * @var Common_Logger_REST_API|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Common_Logger_REST_API
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
     * Initialize REST API
     */
    public function init() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('common-logger/v1', '/report', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_report'),
                'permission_callback' => array($this, 'check_permissions'),
                'args' => array(
                    'limit' => array(
                        'default' => 50,
                        'sanitize_callback' => 'absint',
                    ),
                    'level' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'plugin' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'theme' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'hook' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'search' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ));

        register_rest_route('common-logger/v1', '/insights', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_insights'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
        ));

        register_rest_route('common-logger/v1', '/export', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'export_logs'),
                'permission_callback' => array($this, 'check_permissions'),
                'args' => array(
                    'format' => array(
                        'default' => 'json',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'limit' => array(
                        'default' => 1000,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            ),
        ));
    }

    /**
     * Check API permissions
     *
     * @return bool
     */
    public function check_permissions() {
        return current_user_can('manage_options');
    }

    /**
     * Get logs report
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_report($request) {
        $logger = common_logger();

        $args = array(
            'limit' => $request->get_param('limit'),
            'level' => $request->get_param('level'),
            'plugin' => $request->get_param('plugin'),
            'theme' => $request->get_param('theme'),
            'hook' => $request->get_param('hook'),
            'search' => $request->get_param('search'),
        );

        $logs = $logger->get_logs($args);
        $count = $logger->get_logs_count($args);

        return new WP_REST_Response(array(
            'logs' => $logs,
            'total' => $count,
            'args' => $args,
        ), 200);
    }

    /**
     * Get insights and analytics
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_insights($request) {
        $logger = common_logger();

        // Get error counts by level
        $error_counts = $this->get_error_counts_by_level();

        // Get top plugins with errors
        $top_plugins = $this->get_top_error_plugins();

        // Get top themes with errors
        $top_themes = $this->get_top_error_themes();

        // Get recent error trends
        $error_trends = $this->get_error_trends();

        return new WP_REST_Response(array(
            'error_counts' => $error_counts,
            'top_plugins' => $top_plugins,
            'top_themes' => $top_themes,
            'error_trends' => $error_trends,
            'generated_at' => current_time('mysql'),
        ), 200);
    }

    /**
     * Export logs
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function export_logs($request) {
        $logger = common_logger();
        $format = $request->get_param('format');
        $limit = $request->get_param('limit');

        $logs = $logger->get_logs(array('limit' => $limit));

        // Apply export format filter
        $export_data = array_map(function($log) use ($format) {
            return apply_filters('common_logger_export_format', $log, $format);
        }, $logs);

        switch ($format) {
            case 'csv':
                $csv_content = $this->generate_csv($export_data);
                return new WP_REST_Response($csv_content, 200, array(
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="common-logger-export.csv"',
                ));

            case 'json':
            default:
                return new WP_REST_Response(array(
                    'export' => $export_data,
                    'format' => 'json',
                    'count' => count($export_data),
                    'generated_at' => current_time('mysql'),
                ), 200);
        }
    }

    /**
     * Get error counts by level
     *
     * @return array
     */
    private function get_error_counts_by_level() {
        global $wpdb;

        $logger = common_logger();
        if (Common_Logger::STORAGE_DATABASE !== $logger->get_storage_mode()) {
            return array();
        }

        $table = $logger->get_table_name();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT level, COUNT(*) as count FROM {$table} WHERE timestamp >= %s GROUP BY level ORDER BY count DESC",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));

        $counts = array();
        foreach ($results as $result) {
            $counts[$result->level] = intval($result->count);
        }

        return $counts;
    }

    /**
     * Get top plugins with errors
     *
     * @return array
     */
    private function get_top_error_plugins() {
        global $wpdb;

        $logger = common_logger();
        if (Common_Logger::STORAGE_DATABASE !== $logger->get_storage_mode()) {
            return array();
        }

        $table = $logger->get_table_name();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT plugin, COUNT(*) as count FROM {$table} WHERE plugin != '' AND timestamp >= %s GROUP BY plugin ORDER BY count DESC LIMIT 10",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));

        return array_map(function($result) {
            return array(
                'plugin' => $result->plugin,
                'error_count' => intval($result->count),
            );
        }, $results);
    }

    /**
     * Get top themes with errors
     *
     * @return array
     */
    private function get_top_error_themes() {
        global $wpdb;

        $logger = common_logger();
        if (Common_Logger::STORAGE_DATABASE !== $logger->get_storage_mode()) {
            return array();
        }

        $table = $logger->get_table_name();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT theme, COUNT(*) as count FROM {$table} WHERE theme != '' AND timestamp >= %s GROUP BY theme ORDER BY count DESC LIMIT 10",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));

        return array_map(function($result) {
            return array(
                'theme' => $result->theme,
                'error_count' => intval($result->count),
            );
        }, $results);
    }

    /**
     * Get error trends over time
     *
     * @return array
     */
    private function get_error_trends() {
        global $wpdb;

        $logger = common_logger();
        if (Common_Logger::STORAGE_DATABASE !== $logger->get_storage_mode()) {
            return array();
        }

        $table = $logger->get_table_name();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(timestamp) as date, COUNT(*) as count FROM {$table} WHERE timestamp >= %s GROUP BY DATE(timestamp) ORDER BY date DESC LIMIT 30",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));

        return array_map(function($result) {
            return array(
                'date' => $result->date,
                'error_count' => intval($result->count),
            );
        }, array_reverse($results));
    }

    /**
     * Generate CSV content from log data
     *
     * @param array $logs Log data
     * @return string
     */
    private function generate_csv($logs) {
        if (empty($logs)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Write CSV header
        fputcsv($output, array(
            'Timestamp',
            'Level',
            'Message',
            'Plugin',
            'Theme',
            'File',
            'Line',
            'Hook',
            'Function Chain'
        ));

        // Write log entries
        foreach ($logs as $log) {
            fputcsv($output, array(
                isset($log['timestamp']) ? $log['timestamp'] : '',
                isset($log['level']) ? $log['level'] : '',
                isset($log['message']) ? $log['message'] : '',
                isset($log['plugin']) ? $log['plugin'] : '',
                isset($log['theme']) ? $log['theme'] : '',
                isset($log['file']) ? $log['file'] : '',
                isset($log['line']) ? $log['line'] : '',
                isset($log['hook']) ? $log['hook'] : '',
                isset($log['function_chain']) ? implode(' | ', $log['function_chain']) : '',
            ));
        }

        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        return $csv_content;
    }
}