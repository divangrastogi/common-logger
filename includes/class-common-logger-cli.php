<?php
/**
 * Common Logger WP-CLI commands.
 *
 * Provides CLI utilities for inspecting and managing log entries.
 *
 * @package CommonLogger
 * @version 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Common_Logger_CLI')) {
    /**
     * WP-CLI command handler.
     */
    class Common_Logger_CLI {
        /**
         * List log entries.
         *
         * ## OPTIONS
         *
         * [--level=<level>]
         * : Filter by level (ERROR, WARNING, NOTICE, INFO, DEBUG).
         *
         * [--plugin=<plugin>]
         * : Filter by originating plugin slug.
         *
         * [--search=<term>]
         * : Search within log messages and context.
         *
         * [--limit=<number>]
         * : Number of entries to return. Default 50.
         *
         * [--format=<format>]
         * : Format to display the data. table, json, csv, yaml.
         * ---
         * default: table
         * options:
         *   - table
         *   - json
         *   - csv
         *   - yaml
         * ---
         *
         * ## EXAMPLES
         *
         *     wp common-logger list --level=ERROR --limit=25
         *
         * @param array $args positional args.
         * @param array $assoc_args associative args.
         */
        public function list_($args, $assoc_args) {
            $assoc_args = wp_parse_args($assoc_args, array(
                'level'  => '',
                'plugin' => '',
                'search' => '',
                'limit'  => 50,
                'format' => 'table',
            ));

            $level = common_logger_sanitize_level($assoc_args['level']);
            $limit = max(1, (int) $assoc_args['limit']);

            $logs = common_logger()->get_logs(array(
                'limit'  => $limit,
                'level'  => $level,
                'plugin' => $assoc_args['plugin'],
                'search' => $assoc_args['search'],
            ));

            if (empty($logs)) {
                WP_CLI::log('No log entries found.');
                return;
            }

            $items = array();
            foreach ($logs as $log) {
                $items[] = array(
                    'time'    => isset($log['logged_at']) ? $log['logged_at'] : '',
                    'level'   => isset($log['level']) ? strtoupper($log['level']) : '',
                    'plugin'  => isset($log['origin_plugin']) ? $log['origin_plugin'] : '',
                    'issue'   => isset($log['issue_summary']) ? $log['issue_summary'] : '',
                    'message' => isset($log['message']) ? $log['message'] : '',
                );
            }

            WP_CLI\Utils\format_items($assoc_args['format'], $items, array('time', 'level', 'plugin', 'issue', 'message'));
        }

        /**
         * Clear all log entries using the current storage backend.
         *
         * ## EXAMPLES
         *
         *     wp common-logger clear
         */
        public function clear($args, $assoc_args) {
            common_logger()->clear_logs();
            WP_CLI::success('Logs cleared successfully.');
        }

        /**
         * Purge log entries from database storage with filters.
         *
         * ## OPTIONS
         *
         * [--level=<level>]
         * : Filter by level when purging.
         *
         * [--plugin=<plugin>]
         * : Filter by plugin slug when purging.
         *
         * [--search=<term>]
         * : Search term to match within message/context.
         *
         * [--dry-run]
         * : Show how many rows would be deleted without removing them.
         *
         * ## EXAMPLES
         *
         *     wp common-logger purge --level=ERROR --plugin=woocommerce
         */
        public function purge($args, $assoc_args) {
            $storage_mode = common_logger()->get_storage_mode();
            if (Common_Logger::STORAGE_DATABASE !== $storage_mode) {
                WP_CLI::error('Purge is only available when using database storage.');
            }

            $filters = array(
                'level'  => isset($assoc_args['level']) ? common_logger_sanitize_level($assoc_args['level']) : '',
                'plugin' => isset($assoc_args['plugin']) ? strtolower($assoc_args['plugin']) : '',
                'search' => isset($assoc_args['search']) ? $assoc_args['search'] : '',
            );

            $dry_run = isset($assoc_args['dry-run']);

            if ($dry_run) {
                $logs = common_logger()->get_logs(array_merge($filters, array('limit' => 1000, 'fetch_limit' => 0)));
                WP_CLI::log(sprintf('Dry run: %d matching rows found.', count($logs)));
                return;
            }

            if (!method_exists(common_logger(), 'purge_database_logs')) {
                WP_CLI::error('Purge functionality is unavailable in this version of Common Logger.');
            }

            $deleted = common_logger()->purge_database_logs($filters);
            WP_CLI::success(sprintf('%d log entries deleted.', $deleted));
        }

        /**
         * Export log entries to a file on disk.
         *
         * ## OPTIONS
         *
         * <path>
         * : Absolute or relative path where the export should be written.
         *
         * [--format=<format>]
         * : File format: json or csv.
         * ---
         * default: json
         * options:
         *   - json
         *   - csv
         * ---
         *
         * [--limit=<number>]
         * : Number of log entries to export. Default 200.
         *
         * ## EXAMPLES
         *
         *     wp common-logger export ./logs/common-logger.json --limit=500
         */
        public function export($args, $assoc_args) {
            list($path) = $args;

            $assoc_args = wp_parse_args(
                $assoc_args,
                array(
                    'format' => 'json',
                    'limit'  => 200,
                )
            );

            $format = strtolower($assoc_args['format']);
            $limit  = max(1, (int) $assoc_args['limit']);

            $logs = common_logger()->get_logs(
                array(
                    'limit' => $limit,
                )
            );

            if (empty($logs)) {
                WP_CLI::warning('No log entries found to export.');
                return;
            }

            $path = WP_CLI::get_runner()->is_windows() && false === strpos($path, ':')
                ? getcwd() . DIRECTORY_SEPARATOR . $path
                : $path;

            if ('csv' === $format) {
                $this->export_csv($path, $logs);
            } else {
                $this->export_json($path, $logs);
            }

            WP_CLI::success(sprintf('Exported %d log entries to %s', count($logs), $path));
        }

        /**
         * Continuously stream new log entries to the console.
         *
         * ## OPTIONS
         *
         * [--interval=<seconds>]
         * : Polling interval in seconds. Default 5.
         *
         * [--limit=<number>]
         * : Number of recent entries to load initially. Default 50.
         *
         * ## EXAMPLES
         *
         *     wp common-logger tail --interval=3 --limit=25
         */
        public function tail($args, $assoc_args) {
            $options = wp_parse_args(
                $assoc_args,
                array(
                    'interval' => 5,
                    'limit'    => 50,
                )
            );

            $interval = max(1, (int) $options['interval']);
            $limit    = max(1, (int) $options['limit']);

            $last_seen = 0;

            WP_CLI::log('Tailing Common Logger output. Press Ctrl+C to stop.');

            while (true) {
                $logs = common_logger()->get_logs(
                    array(
                        'limit'       => $limit,
                        'fetch_limit' => $limit,
                    )
                );

                $logs = array_reverse($logs);

                foreach ($logs as $entry) {
                    $hash = md5(wp_json_encode($entry));
                    if ($hash === $last_seen) {
                        continue;
                    }

                    $last_seen = $hash;
                    $output    = sprintf(
                        '[%s] %s: %s',
                        isset($entry['logged_at']) ? $entry['logged_at'] : '-',
                        isset($entry['level']) ? strtoupper($entry['level']) : 'INFO',
                        isset($entry['message']) ? $entry['message'] : ''
                    );

                    WP_CLI::log($output);
                }

                sleep($interval);
            }
        }

        /**
         * View or update performance monitor thresholds.
         *
         * ## OPTIONS
         *
         * [--http-threshold=<seconds>]
         * : New threshold for outbound HTTP monitoring (seconds).
         *
         * [--ajax-threshold=<seconds>]
         * : New threshold for AJAX/REST monitoring (seconds).
         *
         * ## EXAMPLES
         *
         *     wp common-logger settings
         *     wp common-logger settings --http-threshold=2 --ajax-threshold=1.5
         */
        public function settings($args, $assoc_args) {
            $options = get_option('common_logger_options', array());

            $http_threshold = isset($assoc_args['http-threshold']) ? (float) $assoc_args['http-threshold'] : null;
            $ajax_threshold = isset($assoc_args['ajax-threshold']) ? (float) $assoc_args['ajax-threshold'] : null;

            $updated = false;

            if (null !== $http_threshold) {
                $options['http_threshold'] = max(0, $http_threshold);
                $updated = true;
            }

            if (null !== $ajax_threshold) {
                $options['ajax_threshold'] = max(0, $ajax_threshold);
                $updated = true;
            }

            if ($updated) {
                update_option('common_logger_options', $options);
                WP_CLI::success('Monitor thresholds updated.');
            }

            $http_display = isset($options['http_threshold']) ? $options['http_threshold'] : 'default (1.5)';
            $ajax_display = isset($options['ajax_threshold']) ? $options['ajax_threshold'] : 'default (1.0)';

            WP_CLI::log('Current thresholds:');
            WP_CLI::log(sprintf('  HTTP: %s seconds', $http_display));
            WP_CLI::log(sprintf('  AJAX/REST: %s seconds', $ajax_display));
        }

        /**
         * Helper: export logs to JSON file.
         *
         * @param string $path File path.
         * @param array  $logs Log entries.
         */
        private function export_json($path, $logs) {
            $encoded = wp_json_encode($logs, JSON_PRETTY_PRINT);
            if (false === file_put_contents($path, $encoded)) {
                WP_CLI::error(sprintf('Failed to write JSON export to %s', $path));
            }
        }

        /**
         * Helper: export logs to CSV file.
         *
         * @param string $path File path.
         * @param array  $logs Log entries.
         */
        private function export_csv($path, $logs) {
            $handle = fopen($path, 'w');

            if (!$handle) {
                WP_CLI::error(sprintf('Failed to open %s for writing.', $path));
            }

            $headers = array('logged_at', 'level', 'message', 'origin_plugin', 'origin_file');
            fputcsv($handle, $headers);

            foreach ($logs as $log) {
                $row = array(
                    isset($log['logged_at']) ? $log['logged_at'] : '',
                    isset($log['level']) ? strtoupper($log['level']) : '',
                    isset($log['message']) ? $log['message'] : '',
                    isset($log['origin_plugin']) ? $log['origin_plugin'] : '',
                    isset($log['origin_file']) ? $log['origin_file'] : '',
                );

                fputcsv($handle, $row);
            }

            fclose($handle);
        }

        /**
         * Generate error report with insights and top issues.
         *
         * ## OPTIONS
         *
         * [--top=<number>]
         * : Number of top errors/issues to show. Default 10.
         *
         * [--days=<number>]
         * : Number of days to look back. Default 7.
         *
         * [--format=<format>]
         * : Output format. table, json, yaml.
         * ---
         * default: table
         * options:
         *   - table
         *   - json
         *   - yaml
         * ---
         *
         * ## EXAMPLES
         *
         *     wp common-logger report --top=10
         *     wp common-logger report --days=30 --format=json
         *
         * @param array $args positional args.
         * @param array $assoc_args associative args.
         */
        public function report($args, $assoc_args) {
            $assoc_args = wp_parse_args($assoc_args, array(
                'top'    => 10,
                'days'   => 7,
                'format' => 'table',
            ));

            $top = max(1, (int) $assoc_args['top']);
            $days = max(1, (int) $assoc_args['days']);

            WP_CLI::log("Generating error report for the last {$days} days...");

            // Get error counts by level
            $error_counts = $this->get_error_counts_by_level($days);

            // Get top errors by message
            $top_errors = $this->get_top_errors($top, $days);

            // Get top plugins with errors
            $top_plugins = $this->get_top_error_plugins($top, $days);

            // Get top themes with errors
            $top_themes = $this->get_top_error_themes($top, $days);

            $report = array(
                'period' => "{$days} days",
                'generated_at' => current_time('mysql'),
                'error_counts' => $error_counts,
                'top_errors' => $top_errors,
                'top_plugins' => $top_plugins,
                'top_themes' => $top_themes,
            );

            if ('json' === $assoc_args['format']) {
                echo wp_json_encode($report, JSON_PRETTY_PRINT);
            } elseif ('yaml' === $assoc_args['format']) {
                // Simple YAML-like output
                echo "period: {$report['period']}\n";
                echo "generated_at: {$report['generated_at']}\n";
                echo "\nerror_counts:\n";
                foreach ($report['error_counts'] as $level => $count) {
                    echo "  {$level}: {$count}\n";
                }
                echo "\ntop_errors:\n";
                foreach ($report['top_errors'] as $error) {
                    echo "  - message: {$error['message']}\n";
                    echo "    count: {$error['count']}\n";
                    echo "    level: {$error['level']}\n";
                }
                echo "\ntop_plugins:\n";
                foreach ($report['top_plugins'] as $plugin) {
                    echo "  - plugin: {$plugin['plugin']}\n";
                    echo "    error_count: {$plugin['error_count']}\n";
                }
                echo "\ntop_themes:\n";
                foreach ($report['top_themes'] as $theme) {
                    echo "  - theme: {$theme['theme']}\n";
                    echo "    error_count: {$theme['error_count']}\n";
                }
            } else {
                // Table format
                WP_CLI::log("Error Report (Last {$days} days)");
                WP_CLI::log('================================');

                WP_CLI::log("\nError Counts by Level:");
                $items = array();
                foreach ($error_counts as $level => $count) {
                    $items[] = array('level' => $level, 'count' => $count);
                }
                WP_CLI\Utils\format_items('table', $items, array('level', 'count'));

                WP_CLI::log("\nTop {$top} Errors:");
                $items = array();
                foreach ($top_errors as $error) {
                    $items[] = array(
                        'count' => $error['count'],
                        'level' => $error['level'],
                        'message' => substr($error['message'], 0, 100) . (strlen($error['message']) > 100 ? '...' : ''),
                    );
                }
                WP_CLI\Utils\format_items('table', $items, array('count', 'level', 'message'));

                if (!empty($top_plugins)) {
                    WP_CLI::log("\nTop {$top} Plugins with Errors:");
                    WP_CLI\Utils\format_items('table', $top_plugins, array('plugin', 'error_count'));
                }

                if (!empty($top_themes)) {
                    WP_CLI::log("\nTop {$top} Themes with Errors:");
                    WP_CLI\Utils\format_items('table', $top_themes, array('theme', 'error_count'));
                }
            }
        }

        /**
         * Get error counts by level
         *
         * @param int $days Number of days to look back
         * @return array
         */
        private function get_error_counts_by_level($days) {
            global $wpdb;

            $logger = common_logger();
            if (Common_Logger::STORAGE_DATABASE !== $logger->get_storage_mode()) {
                return array('Database storage required for reports' => 0);
            }

            $table = $logger->get_table_name();
            $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT level, COUNT(*) as count FROM {$table} WHERE timestamp >= %s GROUP BY level ORDER BY count DESC",
                $date_threshold
            ));

            $counts = array();
            foreach ($results as $result) {
                $counts[$result->level] = intval($result->count);
            }

            return $counts;
        }

        /**
         * Get top errors by message
         *
         * @param int $top Number of top errors to return
         * @param int $days Number of days to look back
         * @return array
         */
        private function get_top_errors($top, $days) {
            global $wpdb;

            $logger = common_logger();
            if (Common_Logger::STORAGE_DATABASE !== $logger->get_storage_mode()) {
                return array();
            }

            $table = $logger->get_table_name();
            $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT message, level, COUNT(*) as count FROM {$table} WHERE timestamp >= %s GROUP BY message, level ORDER BY count DESC LIMIT %d",
                $date_threshold,
                $top
            ));

            return array_map(function($result) {
                return array(
                    'message' => $result->message,
                    'level' => $result->level,
                    'count' => intval($result->count),
                );
            }, $results);
        }

        /**
         * Get top plugins with errors
         *
         * @param int $top Number of top plugins to return
         * @param int $days Number of days to look back
         * @return array
         */
        private function get_top_error_plugins($top, $days) {
            global $wpdb;

            $logger = common_logger();
            if (Common_Logger::STORAGE_DATABASE !== $logger->get_storage_mode()) {
                return array();
            }

            $table = $logger->get_table_name();
            $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT plugin, COUNT(*) as error_count FROM {$table} WHERE plugin != '' AND timestamp >= %s GROUP BY plugin ORDER BY error_count DESC LIMIT %d",
                $date_threshold,
                $top
            ));

            return array_map(function($result) {
                return array(
                    'plugin' => $result->plugin,
                    'error_count' => intval($result->error_count),
                );
            }, $results);
        }

        /**
         * Get top themes with errors
         *
         * @param int $top Number of top themes to return
         * @param int $days Number of days to look back
         * @return array
         */
        private function get_top_error_themes($top, $days) {
            global $wpdb;

            $logger = common_logger();
            if (Common_Logger::STORAGE_DATABASE !== $logger->get_storage_mode()) {
                return array();
            }

            $table = $logger->get_table_name();
            $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT theme, COUNT(*) as error_count FROM {$table} WHERE theme != '' AND timestamp >= %s GROUP BY theme ORDER BY error_count DESC LIMIT %d",
                $date_threshold,
                $top
            ));

            return array_map(function($result) {
                return array(
                    'theme' => $result->theme,
                    'error_count' => intval($result->error_count),
                );
            }, $results);
        }
    }
}

if (class_exists('WP_CLI')) {
    WP_CLI::add_command('common-logger', 'Common_Logger_CLI');
}
