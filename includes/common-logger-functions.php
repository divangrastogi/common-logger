<?php
/**
 * Common Logger General Functions
 *
 * Contains general utility functions for the Common Logger plugin.
 *
 * @package CommonLogger
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Common Logger instance
 *
 * @return Common_Logger
 */
function common_logger() {
    return Common_Logger::instance();
}

/**
 * Get plugin label from plugin slug
 *
 * @param string $plugin_slug Plugin slug
 * @return string Plugin label
 */
function common_logger_get_plugin_label($plugin_slug) {
    if (empty($plugin_slug)) {
        return __('Unknown', 'common-logger-utility');
    }

    // Common plugin mappings
    $plugin_labels = array(
        'woocommerce' => 'WooCommerce',
        'wordpress-seo' => 'Yoast SEO',
        'wp-rocket' => 'WP Rocket',
        'contact-form-7' => 'Contact Form 7',
        'elementor' => 'Elementor',
        'wpforms' => 'WPForms',
        'gravityforms' => 'Gravity Forms',
        'mailchimp-for-wp' => 'Mailchimp for WordPress',
        'wordfence' => 'Wordfence',
        'akismet' => 'Akismet',
        'jetpack' => 'Jetpack',
        'updraftplus' => 'UpdraftPlus',
        'wp-super-cache' => 'WP Super Cache',
        'w3-total-cache' => 'W3 Total Cache',
        'autoptimize' => 'Autoptimize',
        'smush' => 'Smush',
        'ithemes-security' => 'iThemes Security',
        'duplicate-post' => 'Duplicate Post',
        'redirection' => 'Redirection',
        'broken-link-checker' => 'Broken Link Checker',
        'wp-mail-smtp' => 'WP Mail SMTP',
        'wp-optimize' => 'WP Optimize',
        'backwpup' => 'BackWPup',
    );

    // Check if it's a known plugin
    if (isset($plugin_labels[$plugin_slug])) {
        return $plugin_labels[$plugin_slug];
    }

    // Try to get from plugin data
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $all_plugins = get_plugins();
    foreach ($all_plugins as $file => $data) {
        $slug = dirname($file);
        if ($slug === $plugin_slug || $file === $plugin_slug . '/' . $plugin_slug . '.php') {
            return $data['Name'];
        }
    }

    // Fallback: format the slug nicely
    return ucwords(str_replace(array('-', '_'), ' ', $plugin_slug));
}

/**
 * Get Tools page URL
 *
 * @return string Tools page URL
 */
function common_logger_get_tools_page_url() {
    return add_query_arg(array('page' => 'common_logger_tools'), admin_url('tools.php'));
}

/**
 * Check if current user can manage options
 *
 * @return bool
 */
function common_logger_current_user_can_manage() {
    return current_user_can('manage_options');
}

/**
 * Sanitize and validate log level
 *
 * @param string $level Log level
 * @return string Sanitized level
 */
function common_logger_sanitize_level($level) {
    $valid_levels = array('ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG');
    $level = strtoupper(trim($level));

    return in_array($level, $valid_levels, true) ? $level : '';
}

/**
 * Format timestamp for display
 *
 * @param string $timestamp Timestamp
 * @return string Formatted timestamp
 */
function common_logger_format_timestamp($timestamp) {
    if (empty($timestamp)) {
        return '';
    }

    $datetime = strtotime($timestamp);
    if ($datetime === false) {
        return $timestamp;
    }

    return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $datetime);
}

/**
 * Get available log levels for filter dropdown
 *
 * @return array Log levels
 */
function common_logger_get_level_options() {
    return array(
        '' => __('All levels', 'common-logger-utility'),
        'ERROR' => 'ERROR',
        'WARNING' => 'WARNING',
        'NOTICE' => 'NOTICE',
        'INFO' => 'INFO',
        'DEBUG' => 'DEBUG',
    );
}

/**
 * Determine origin metadata (plugin/file) for the current log call.
 *
 * @return array{
 *     plugin: string|null,
 *     file: string|null
 * }
 */
function common_logger_detect_origin_metadata() {
    $origin = array(
        'plugin' => null,
        'file'   => null,
    );

    $backtrace = function_exists('debug_backtrace')
        ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        : array();

    if (empty($backtrace)) {
        return $origin;
    }

    if (!defined('WP_PLUGIN_DIR')) {
        return $origin;
    }

    $plugins_dir = trailingslashit(WP_PLUGIN_DIR);

    foreach ($backtrace as $frame) {
        if (empty($frame['file'])) {
            continue;
        }

        $file = wp_normalize_path($frame['file']);

        if (0 !== strpos($file, $plugins_dir)) {
            continue;
        }

        $relative_path = substr($file, strlen($plugins_dir));
        $parts         = explode('/', $relative_path);

        if (empty($parts)) {
            continue;
        }

        $plugin_slug = $parts[0];

        // Skip Common Logger itself; capture the first external plugin in stack.
        if (strpos($plugin_slug, 'common-logger') === 0) {
            continue;
        }

        $origin['plugin'] = $plugin_slug;
        $origin['file']   = $relative_path;
        break;
    }

    if (!$origin['plugin'] && !$origin['file']) {
        foreach ($backtrace as $frame) {
            if (empty($frame['file'])) {
                continue;
            }

            $file = wp_normalize_path($frame['file']);

            if (false !== strpos($file, 'common-logger')) {
                continue;
            }

            $origin['file'] = $file;
            break;
        }
    }

    return $origin;
}

/**
 * Enhanced origin detection including theme and hook information
 *
 * This function is already implemented in common-logger.php as common_logger_detect_enhanced_origin_metadata()
 * Keeping this for backward compatibility and to provide a utility function wrapper.
 *
 * @param string $file File path
 * @param int $line Line number
 * @return array Enhanced origin metadata
 */
function common_logger_detect_enhanced_origin_metadata($file = '', $line = 0) {
    // If no file/line provided, detect from current context
    if (empty($file)) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($backtrace as $frame) {
            if (isset($frame['file']) && strpos($frame['file'], 'common-logger') === false) {
                $file = $frame['file'];
                $line = isset($frame['line']) ? $frame['line'] : 0;
                break;
            }
        }
    }

    $metadata = array(
        'plugin' => '',
        'theme' => '',
        'file' => $file,
        'line' => $line,
        'hook' => '',
    );

    // Detect plugin
    if (strpos($file, WP_PLUGIN_DIR) === 0) {
        $plugin_path = str_replace(WP_PLUGIN_DIR . '/', '', $file);
        $parts = explode('/', $plugin_path);
        $metadata['plugin'] = $parts[0];
    }

    // Detect theme
    $theme_root = get_theme_root();
    if (strpos($file, $theme_root) === 0) {
        $theme_path = str_replace($theme_root . '/', '', $file);
        $parts = explode('/', $theme_path);
        $metadata['theme'] = $parts[0];
    }

    // Detect current hook if available
    if (function_exists('current_filter')) {
        $metadata['hook'] = current_filter();
    }

    return $metadata;
}

/**
 * Build function chain from debug backtrace
 *
 * @param int $max_depth Maximum depth to trace
 * @return array Function chain
 */
function common_logger_build_function_chain($max_depth = 10) {
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $chain = array();

    foreach ($backtrace as $frame) {
        if (isset($frame['function'])) {
            $function_name = $frame['function'];

            if (isset($frame['class'])) {
                $function_name = $frame['class'] . '::' . $function_name;
            }

            // Skip common logger functions to avoid self-logging
            if (strpos($function_name, 'common_logger') === false) {
                $chain[] = $function_name;
            }
        }

        if (count($chain) >= $max_depth) {
            break;
        }
    }

    return array_slice($chain, 0, $max_depth);
}

/**
 * Get theme label from theme slug
 *
 * @param string $theme_slug Theme slug
 * @return string Theme label
 */
function common_logger_get_theme_label($theme_slug) {
    if (empty($theme_slug)) {
        return __('Unknown', 'common-logger-utility');
    }

    // Try to get from theme data
    $themes = wp_get_themes();
    if (isset($themes[$theme_slug])) {
        return $themes[$theme_slug]->get('Name');
    }

    // Common theme mappings
    $theme_labels = array(
        'twentytwentyone' => 'Twenty Twenty-One',
        'twentytwentytwo' => 'Twenty Twenty-Two',
        'twentytwentythree' => 'Twenty Twenty-Three',
        'twentytwentyfour' => 'Twenty Twenty-Four',
        'twentytwentyfive' => 'Twenty Twenty-Five',
        'astra' => 'Astra',
        'generatepress' => 'GeneratePress',
        'oceanwp' => 'OceanWP',
        'avada' => 'Avada',
        'divi' => 'Divi',
        'enfold' => 'Enfold',
        'betheme' => 'BeTheme',
        'the7' => 'The7',
        'flatsome' => 'Flatsome',
        'woodmart' => 'WoodMart',
        'salient' => 'Salient',
    );

    if (isset($theme_labels[$theme_slug])) {
        return $theme_labels[$theme_slug];
    }

    // Fallback: format the slug nicely
    return ucwords(str_replace(array('-', '_'), ' ', $theme_slug));
}

/**
 * Get available themes for filter dropdown
 *
 * @return array Theme options
 */
function common_logger_get_theme_options() {
    $themes = wp_get_themes();
    $options = array('' => __('All themes', 'common-logger-utility'));

    foreach ($themes as $theme_slug => $theme) {
        $options[$theme_slug] = $theme->get('Name');
    }

    return $options;
}

/**
 * Get available hooks for filter dropdown
 *
 * @return array Hook options
 */
function common_logger_get_hook_options() {
    return array(
        '' => __('All hooks', 'common-logger-utility'),
        'wp_head' => 'wp_head',
        'wp_footer' => 'wp_footer',
        'init' => 'init',
        'wp_loaded' => 'wp_loaded',
        'plugins_loaded' => 'plugins_loaded',
        'wp_enqueue_scripts' => 'wp_enqueue_scripts',
        'admin_enqueue_scripts' => 'admin_enqueue_scripts',
        'wp_ajax_*' => 'wp_ajax_*',
        'woocommerce_*' => 'woocommerce_*',
        'elementor/*' => 'elementor/*',
    );
}

/**
 * Format function chain for display
 *
 * @param array $chain Function chain
 * @param int $max_length Maximum display length
 * @return string Formatted chain
 */
function common_logger_format_function_chain($chain, $max_length = 100) {
    if (empty($chain)) {
        return __('No chain available', 'common-logger-utility');
    }

    $formatted = implode(' → ', $chain);

    if (strlen($formatted) > $max_length) {
        $formatted = substr($formatted, 0, $max_length - 3) . '...';
    }

    return $formatted;
}

/**
 * Check if a string contains sensitive information
 *
 * @param string $string String to check
 * @return bool True if contains sensitive info
 */
function common_logger_contains_sensitive_info($string) {
    $sensitive_patterns = array(
        '/password/i',
        '/passwd/i',
        '/secret/i',
        '/key/i',
        '/token/i',
        '/api[_-]?key/i',
        '/auth[_-]?key/i',
        '/private[_-]?key/i',
        '/access[_-]?token/i',
        '/bearer/i',
        '/authorization/i',
        '/cookie/i',
        '/session/i',
    );

    foreach ($sensitive_patterns as $pattern) {
        if (preg_match($pattern, $string)) {
            return true;
        }
    }

    return false;
}

/**
 * Sanitize context data for logging
 *
 * @param array $context Context data
 * @return array Sanitized context
 */
function common_logger_sanitize_context($context) {
    $sanitized = array();

    foreach ($context as $key => $value) {
        // Skip sensitive keys
        if (common_logger_contains_sensitive_info($key)) {
            $sanitized[$key] = '[REDACTED]';
            continue;
        }

        // Sanitize values
        if (is_string($value)) {
            // Remove potential sensitive data from strings
            if (common_logger_contains_sensitive_info($value)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        } elseif (is_array($value)) {
            $sanitized[$key] = common_logger_sanitize_context($value);
        } elseif (is_object($value)) {
            $sanitized[$key] = '[Object: ' . get_class($value) . ']';
        } else {
            $sanitized[$key] = $value;
        }
    }

    return $sanitized;
}

/**
 * Get error insights summary
 *
 * @param int $days Number of days to look back
 * @return array Error insights
 */
function common_logger_get_error_insights($days = 7) {
    $logger = common_logger();

    if (Common_Logger::STORAGE_DATABASE !== $logger->get_storage_mode()) {
        return array('error' => 'Database storage required for insights');
    }

    global $wpdb;
    $table = $logger->get_table_name();
    $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

    // Get error counts by level
    $error_counts = $wpdb->get_results($wpdb->prepare(
        "SELECT level, COUNT(*) as count FROM {$table} WHERE timestamp >= %s GROUP BY level ORDER BY count DESC",
        $date_threshold
    ), ARRAY_A);

    // Get top plugins with errors
    $top_plugins = $wpdb->get_results($wpdb->prepare(
        "SELECT plugin, COUNT(*) as error_count FROM {$table} WHERE plugin != '' AND timestamp >= %s GROUP BY plugin ORDER BY error_count DESC LIMIT 10",
        $date_threshold
    ), ARRAY_A);

    // Get top themes with errors
    $top_themes = $wpdb->get_results($wpdb->prepare(
        "SELECT theme, COUNT(*) as error_count FROM {$table} WHERE theme != '' AND timestamp >= %s GROUP BY theme ORDER BY error_count DESC LIMIT 10",
        $date_threshold
    ), ARRAY_A);

    // Get error trends
    $error_trends = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(timestamp) as date, COUNT(*) as error_count FROM {$table} WHERE timestamp >= %s GROUP BY DATE(timestamp) ORDER BY date ASC",
        $date_threshold
    ), ARRAY_A);

    return array(
        'period_days' => $days,
        'error_counts' => array_column($error_counts, 'count', 'level'),
        'top_plugins' => $top_plugins,
        'top_themes' => $top_themes,
        'error_trends' => $error_trends,
        'generated_at' => current_time('mysql'),
    );
}
