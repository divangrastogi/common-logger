<?php
/**
 * Plugin Name: Common Logger
 * Plugin URI: https://example.com/common-logger
 * Description: A comprehensive logging utility for WordPress that captures hook execution, errors, and custom events with database or file storage options.
 * Version: 1.0.0
 * Author: Divang Rastogi
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: common-logger-utility
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.2
 *
 * @package CommonLogger
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('COMMON_LOGGER_VERSION', '1.0.0');
define('COMMON_LOGGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('COMMON_LOGGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('COMMON_LOGGER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include the main Common_Logger class
require_once COMMON_LOGGER_PLUGIN_DIR . 'includes/class-common-logger.php';

// Include general functions
require_once COMMON_LOGGER_PLUGIN_DIR . 'includes/common-logger-functions.php';

// Include performance monitor
require_once COMMON_LOGGER_PLUGIN_DIR . 'includes/class-common-logger-monitor.php';

// Include REST API support
require_once COMMON_LOGGER_PLUGIN_DIR . 'includes/class-common-logger-rest-api.php';

// Include admin class
if (is_admin()) {
    require_once COMMON_LOGGER_PLUGIN_DIR . 'admin/class-common-logger-admin.php';
}

// Include CLI support when running under WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    require_once COMMON_LOGGER_PLUGIN_DIR . 'includes/class-common-logger-cli.php';
}

// Include optional tool integrations
if (get_option('common_logger_enable_tool_integrations', false)) {
    require_once COMMON_LOGGER_PLUGIN_DIR . 'includes/class-common-logger-integrations.php';
}

// Initialize the plugin
function common_logger_init() {
    // Load text domain
    load_plugin_textdomain('common-logger-utility', false, dirname(COMMON_LOGGER_PLUGIN_BASENAME) . '/languages/');

    // Initialize admin functionality
    if (is_admin()) {
        new Common_Logger_Admin();
    }

    // Bootstrap performance monitoring utilities
    Common_Logger_Monitor::instance()->bootstrap();

    // Initialize AI error detection and tool integrations
    common_logger_initialize_ai_features();

    // Initialize REST API
    if (get_option('common_logger_enable_rest_api', true)) {
        Common_Logger_REST_API::instance()->init();
    }
}
add_action('plugins_loaded', 'common_logger_init');

// Activation hook
register_activation_hook(__FILE__, 'common_logger_activate');

/**
 * Plugin activation
 */
function common_logger_activate() {
    // Create necessary database tables if needed
    $logger = common_logger();
    if (method_exists($logger, 'create_tables')) {
        $logger->create_tables();
    }

    // Set default options
    add_option('common_logger_options', array(
        'storage_mode' => Common_Logger::STORAGE_FILE,
        'error_only_mode' => false,
        'hook_tracing_enabled' => false,
        'hook_prefix' => '',
        'php_error_enabled' => true,
        'slow_query_enabled' => false,
        'slow_query_threshold' => 0.5,
        'enable_ai_insights' => true,
        'enable_tool_integrations' => false,
        'enable_rest_api' => true,
        'developer_mode' => false,
        'notification_threshold' => 10,
    ));

    // Flush rewrite rules if needed
    if (function_exists('flush_rewrite_rules')) {
        flush_rewrite_rules();
    }
}

/**
 * Initialize AI error detection and tool integrations
 */
function common_logger_initialize_ai_features() {
    // Register custom error handler for enhanced error detection
    if (get_option('common_logger_enable_ai_insights', true)) {
        //set_error_handler('common_logger_enhanced_error_handler');
        //set_exception_handler('common_logger_enhanced_exception_handler');
        //register_shutdown_function('common_logger_shutdown_error_handler');
    }

    // Initialize tool integrations if enabled
    if (get_option('common_logger_enable_tool_integrations', false)) {
        Common_Logger_Integrations::instance()->init();
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'common_logger_deactivate');

/**
 * Plugin deactivation
 */
function common_logger_deactivate() {
    // Flush rewrite rules
    if (function_exists('flush_rewrite_rules')) {
        flush_rewrite_rules();
    }
}

// Prevent the plugin from logging its own actions
add_filter('common_logger_should_log', 'common_logger_prevent_self_logging', 10, 2);

// Add custom hooks for enhanced functionality
add_filter('common_logger_pre_log', 'common_logger_pre_log_processor', 10, 1);
add_action('common_logger_post_log', 'common_logger_post_log_processor', 10, 3);
add_filter('common_logger_export_format', 'common_logger_export_format_processor', 10, 2);

/**
 * Enhanced error handler with AI-powered analysis
 *
 * @param int $errno Error level
 * @param string $errstr Error message
 * @param string $errfile Error file
 * @param int $errline Error line
 * @return bool
 */
function common_logger_enhanced_error_handler($errno, $errstr, $errfile, $errline) {
    // Only handle errors that are included in error_reporting
    if (!(error_reporting() & $errno)) {
        return false;
    }

    // Skip logging certain notices that are not critical
    if ($errno === E_NOTICE && strpos($errstr, '_load_textdomain_just_in_time') !== false) {
        return false;
    }

    // Skip logging if we're already in an error logging context to prevent loops
    static $logging_error = false;
    if ($logging_error) {
        return false;
    }

    $error_levels = array(
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'ERROR',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'ERROR',
        E_CORE_WARNING => 'WARNING',
        E_COMPILE_ERROR => 'ERROR',
        E_COMPILE_WARNING => 'WARNING',
        E_USER_ERROR => 'ERROR',
        E_USER_WARNING => 'WARNING',
        E_USER_NOTICE => 'NOTICE',
        E_STRICT => 'NOTICE',
        E_RECOVERABLE_ERROR => 'ERROR',
        E_DEPRECATED => 'NOTICE',
        E_USER_DEPRECATED => 'NOTICE',
    );

    $level = isset($error_levels[$errno]) ? $error_levels[$errno] : 'ERROR';

    $logging_error = true;
    try {
        $context = array(
            'error_type' => $errno,
            'error_file' => $errfile,
            'error_line' => $errline,
            'function_chain' => common_logger_build_function_chain(),
            'origin_metadata' => common_logger_detect_enhanced_origin_metadata($errfile, $errline),
        );

        common_logger()->log($errstr, $level, $context);
    } catch (Exception $e) {
        // If logging fails, don't crash the site
        error_log('Common Logger: Failed to log error: ' . $e->getMessage());
    } finally {
        $logging_error = false;
    }

    // Don't execute PHP's internal error handler
    return true;
}

/**
 * Enhanced exception handler
 *
 * @param Throwable $exception The exception
 */
function common_logger_enhanced_exception_handler($exception) {
    // Skip logging if we're already in an error logging context to prevent loops
    static $logging_exception = false;
    if ($logging_exception) {
        return;
    }

    $logging_exception = true;
    try {
        $context = array(
            'exception_class' => get_class($exception),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'exception_trace' => $exception->getTraceAsString(),
            'function_chain' => common_logger_build_function_chain(),
            'origin_metadata' => common_logger_detect_enhanced_origin_metadata(
                $exception->getFile(),
                $exception->getLine()
            ),
        );

        common_logger()->error($exception->getMessage(), $context);
    } catch (Exception $e) {
        // If logging fails, don't crash the site
        error_log('Common Logger: Failed to log exception: ' . $e->getMessage());
    } finally {
        $logging_exception = false;
    }
}

/**
 * Shutdown error handler for fatal errors
 */
function common_logger_shutdown_error_handler() {
    // Skip logging if we're already in an error logging context to prevent loops
    static $logging_shutdown = false;
    if ($logging_shutdown) {
        return;
    }

    $error = error_get_last();

    if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
        $logging_shutdown = true;
        try {
            $context = array(
                'error_type' => $error['type'],
                'error_file' => $error['file'],
                'error_line' => $error['line'],
                'shutdown_error' => true,
                'function_chain' => common_logger_build_function_chain(),
                'origin_metadata' => common_logger_detect_enhanced_origin_metadata(
                    $error['file'],
                    $error['line']
                ),
            );

            common_logger()->error($error['message'], $context);
        } catch (Exception $e) {
            // If logging fails, don't crash the site
            error_log('Common Logger: Failed to log shutdown error: ' . $e->getMessage());
        } finally {
            $logging_shutdown = false;
        }
    }
}





/**
 * Prevent self-logging filter
 *
 * @param bool $should_log Whether to log the entry
 * @param array $context Log context
 * @return bool Modified should_log flag
 */
function common_logger_prevent_self_logging($should_log, $context) {
    // Prevent logging if it's from the common-logger plugin itself
    if (isset($context['_origin_file']) && strpos($context['_origin_file'], 'common-logger') !== false) {
        return false;
    }
    if (isset($context['_origin_plugin']) && $context['_origin_plugin'] === 'common-logger') {
        return false;
    }
    if (isset($context['origin_metadata']['file']) && strpos($context['origin_metadata']['file'], 'common-logger') !== false) {
        return false;
    }
    if (isset($context['origin_metadata']['plugin']) && $context['origin_metadata']['plugin'] === 'common-logger') {
        return false;
    }
    // Check backtrace for common-logger
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    foreach ($backtrace as $frame) {
        if (isset($frame['file']) && strpos($frame['file'], 'common-logger') !== false) {
            return false;
        }
    }
    return $should_log;
}

/**
 * Pre-log processor hook
 *
 * @param array $log_data Array containing message and context
 * @return array Modified log data
 */
function common_logger_pre_log_processor($log_data) {
    $message = $log_data['message'];
    $context = $log_data['context'];

    // Add timestamp and enhance context
    $context['_timestamp'] = current_time('mysql');
    $context['_enhanced'] = true;

    // Apply any custom processing here
    return array(
        'message' => $message,
        'context' => $context
    );
}

/**
 * Post-log processor hook
 *
 * @param int $log_id Log entry ID (if database mode)
 * @param string $level Log level
 * @param array $log_data Log data
 */
function common_logger_post_log_processor($log_id, $level, $log_data) {
    // Trigger notifications if threshold exceeded (only for ERROR level)
    if ($level === 'ERROR') {
        $threshold = get_option('common_logger_notification_threshold', 10);
        if ($threshold > 0) {
            common_logger_check_notification_thresholds($log_data, $threshold);
        }
    }
}

/**
 * Export format processor
 *
 * @param array $log_entry Log entry data
 * @param string $format Export format (json, csv, etc.)
 * @return array
 */
function common_logger_export_format_processor($log_entry, $format) {
    // Enhance export format with additional metadata
    if ($format === 'json') {
        $log_entry['export_timestamp'] = current_time('mysql');
        $log_entry['export_version'] = COMMON_LOGGER_VERSION;
    }

    return $log_entry;
}

/**
 * Check notification thresholds and send alerts
 *
 * @param array $log_data Log data
 * @param int $threshold Threshold count
 */
function common_logger_check_notification_thresholds($log_data, $threshold) {
    // Only run if database storage is enabled, as file storage is too slow for real-time counting.
    if (Common_Logger::STORAGE_DATABASE !== common_logger()->get_storage_mode()) {
        return;
    }

    $transient_key = 'common_logger_error_count';
    $error_count = (int) get_transient($transient_key);
    $error_count++;

    // Set transient for 5 minutes (300 seconds)
    set_transient($transient_key, $error_count, 300);

    if ($error_count >= $threshold) {
        // Send notification
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        $subject = sprintf(__('Critical Error Threshold Exceeded on %s', 'common-logger-utility'), $site_name);

        $body = sprintf(
            __("The Common Logger plugin has detected %d errors in the last 5 minutes, exceeding the threshold of %d.\n\n", 'common-logger-utility'),
            $error_count,
            $threshold
        );
        $body .= sprintf(__('Latest Error: %s', 'common-logger-utility'), $log_data['message']) . "\n";
        $body .= sprintf(__('Log Details: %s', 'common-logger-utility'), admin_url('tools.php?page=common_logger_logs')) . "\n";

        wp_mail($admin_email, $subject, $body);

        // Reset the counter after sending the notification
        delete_transient($transient_key);
    }
}
