<?php
/**
 * Common Logger Admin Class
 *
 * Handles all admin functionality for the Common Logger plugin.
 *
 * @package CommonLogger
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Common Logger Admin Class
 */
class Common_Logger_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize admin functionality
     */
    private function init() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'register_admin_pages'));
        add_action('admin_post_common_logger_clear_logs', array($this, 'handle_clear_logs'));
        add_action('admin_head', array($this, 'remove_admin_notices'));
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'common_logger') !== false ||
            strpos($hook, 'tools_page_common_logger') !== false ||
            strpos($hook, 'toplevel_page_common_logger') !== false ||
            strpos($hook, 'common_logger_settings') !== false) {
                
            wp_enqueue_style(
                'common-logger-admin',
                plugin_dir_url(__FILE__) . '../assets/css/common-logger-admin.css',
                array(),
                '1.0.0'
            );

            wp_enqueue_script(
                'common-logger-admin',
                plugin_dir_url(__FILE__) . '../assets/js/common-logger-admin.js',
                array('jquery'),
                '1.0.0',
                true
            );
        }
    }

    /**
     * Register admin pages
     */
    public function register_admin_pages() {
        add_options_page(
            __('Common Logger Settings', 'common-logger-utility'),
            __('Common Logger', 'common-logger-utility'),
            'manage_options',
            'common_logger_settings',
            array($this, 'render_settings_page')
        );

        // Only show Tools page if database storage is enabled
        $logger = common_logger();
        if (Common_Logger::STORAGE_DATABASE === $logger->get_storage_mode()) {
            add_management_page(
                __('Common Logger Viewer', 'common-logger-utility'),
                __('Common Logger', 'common-logger-utility'),
                'manage_options',
                'common_logger_tools',
                array($this, 'render_tools_page')
            );
        }
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'common_logger_options',
            'common_logger_options',
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'common_logger_storage',
            __('Storage Configuration', 'common-logger-utility'),
            array($this, 'render_storage_section'),
            'common_logger_settings'
        );

        add_settings_field(
            'storage_mode',
            __('Storage Mode', 'common-logger-utility'),
            array($this, 'render_storage_mode_field'),
            'common_logger_settings',
            'common_logger_storage'
        );

        add_settings_field(
            'error_only_mode',
            __('Error Only Mode', 'common-logger-utility'),
            array($this, 'render_error_only_mode_field'),
            'common_logger_settings',
            'common_logger_storage'
        );

        add_settings_section(
            'common_logger_ai_features',
            __('AI & Developer Features', 'common-logger-utility'),
            array($this, 'render_ai_features_section'),
            'common_logger_settings'
        );

        add_settings_field(
            'enable_ai_insights',
            __('AI Error Insights', 'common-logger-utility'),
            array($this, 'render_ai_insights_field'),
            'common_logger_settings',
            'common_logger_ai_features'
        );

        add_settings_field(
            'enable_tool_integrations',
            __('Tool Integrations', 'common-logger-utility'),
            array($this, 'render_tool_integrations_field'),
            'common_logger_settings',
            'common_logger_ai_features'
        );

        add_settings_field(
            'enable_rest_api',
            __('REST API', 'common-logger-utility'),
            array($this, 'render_rest_api_field'),
            'common_logger_settings',
            'common_logger_ai_features'
        );

        add_settings_field(
            'developer_mode',
            __('Developer Mode', 'common-logger-utility'),
            array($this, 'render_developer_mode_field'),
            'common_logger_settings',
            'common_logger_ai_features'
        );

        add_settings_field(
            'notification_threshold',
            __('Notification Threshold', 'common-logger-utility'),
            array($this, 'render_notification_threshold_field'),
            'common_logger_settings',
            'common_logger_ai_features'
        );

        add_settings_section(
            'common_logger_tracing',
            __('Hook Tracing & Monitoring', 'common-logger-utility'),
            array($this, 'render_tracing_section'),
            'common_logger_settings'
        );

        add_settings_field(
            'hook_tracing',
            __('Hook Tracing', 'common-logger-utility'),
            array($this, 'render_hook_tracing_field'),
            'common_logger_settings',
            'common_logger_tracing'
        );

        add_settings_field(
            'php_errors',
            __('PHP Error Logging', 'common-logger-utility'),
            array($this, 'render_php_errors_field'),
            'common_logger_settings',
            'common_logger_tracing'
        );

        add_settings_field(
            'slow_query',
            __('Slow Query Logging', 'common-logger-utility'),
            array($this, 'render_slow_query_field'),
            'common_logger_settings',
            'common_logger_tracing'
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input data
     * @return array Sanitized data
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['storage_mode'])) {
            $sanitized['storage_mode'] = in_array($input['storage_mode'], array(Common_Logger::STORAGE_FILE, Common_Logger::STORAGE_DATABASE), true)
                ? $input['storage_mode']
                : Common_Logger::STORAGE_FILE;
        }

        if (isset($input['error_only_mode'])) {
            $sanitized['error_only_mode'] = (bool) $input['error_only_mode'];
        }

        if (isset($input['hook_tracing_enabled'])) {
            $sanitized['hook_tracing_enabled'] = (bool) $input['hook_tracing_enabled'];
        }

        if (isset($input['hook_prefix'])) {
            $sanitized['hook_prefix'] = sanitize_text_field($input['hook_prefix']);
        }

        if (isset($input['php_error_enabled'])) {
            $sanitized['php_error_enabled'] = (bool) $input['php_error_enabled'];
        }

        if (isset($input['slow_query_enabled'])) {
            $sanitized['slow_query_enabled'] = (bool) $input['slow_query_enabled'];
        }

        if (isset($input['slow_query_threshold'])) {
            $sanitized['slow_query_threshold'] = max(0.1, floatval($input['slow_query_threshold']));
        }

        if (isset($input['enable_ai_insights'])) {
            $sanitized['enable_ai_insights'] = (bool) $input['enable_ai_insights'];
        }

        if (isset($input['enable_tool_integrations'])) {
            $sanitized['enable_tool_integrations'] = (bool) $input['enable_tool_integrations'];
        }

        if (isset($input['enable_rest_api'])) {
            $sanitized['enable_rest_api'] = (bool) $input['enable_rest_api'];
        }

        if (isset($input['developer_mode'])) {
            $sanitized['developer_mode'] = (bool) $input['developer_mode'];
        }

        if (isset($input['notification_threshold'])) {
            $sanitized['notification_threshold'] = max(0, intval($input['notification_threshold']));
        }

        return $sanitized;
    }

    /**
     * Remove admin notices on Common Logger pages
     */
    public function remove_admin_notices() {
        $current_screen = get_current_screen();
        if ($current_screen && strpos($current_screen->id, 'common_logger') !== false) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
        }
    }

    /**
     * Render storage section
     */
    public function render_storage_section() {
        echo '<p>' . esc_html__('Configure how logs are stored and what gets logged.', 'common-logger-utility') . '</p>';
    }

    /**
     * Render tracing section
     */
    public function render_tracing_section() {
        echo '<p>' . esc_html__('Configure advanced logging features and monitoring.', 'common-logger-utility') . '</p>';
    }

    /**
     * Render storage mode field
     */
    public function render_storage_mode_field() {
        $logger = common_logger();
        $current_mode = $logger->get_storage_mode();
        ?>
        <fieldset>
            <label>
                <input type="radio" name="common_logger_options[storage_mode]" value="<?php echo esc_attr(Common_Logger::STORAGE_FILE); ?>" <?php checked(Common_Logger::STORAGE_FILE, $current_mode); ?> />
                <?php esc_html_e('File (stored in uploads directory)', 'common-logger-utility'); ?>
            </label>
            <br />
            <label>
                <input type="radio" name="common_logger_options[storage_mode]" value="<?php echo esc_attr(Common_Logger::STORAGE_DATABASE); ?>" <?php checked(Common_Logger::STORAGE_DATABASE, $current_mode); ?> />
                <?php esc_html_e('Database (stored in custom table)', 'common-logger-utility'); ?>
            </label>
        </fieldset>

        <?php if (Common_Logger::STORAGE_FILE === $current_mode) { ?>
            <p>
                <?php esc_html_e('Log file path:', 'common-logger-utility'); ?>
                <code><?php echo esc_html($logger->get_log_file_path()); ?></code>
            </p>
            <?php $file_url = $logger->get_log_file_url(); ?>
            <?php if ($file_url) : ?>
                <p>
                    <a href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Open log file in new tab', 'common-logger-utility'); ?>
                    </a>
                </p>
            <?php else : ?>
                <p><?php esc_html_e('No log file found yet.', 'common-logger-utility'); ?></p>
            <?php endif; ?>
        <?php } elseif (Common_Logger::STORAGE_DATABASE === $current_mode) { ?>
            <p>
                <a href="<?php echo esc_url(common_logger_get_tools_page_url()); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('View log table (Tools page)', 'common-logger-utility'); ?>
                </a>
            </p>
        <?php } ?>

        <p>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=common_logger_clear_logs'), 'common_logger_clear_logs', '_common_logger_nonce')); ?>" class="button button-danger" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs? This action cannot be undone.', 'common-logger-utility'); ?>');">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Clear All Logs', 'common-logger-utility'); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Render error only mode field
     */
    public function render_error_only_mode_field() {
        try {
            $enabled = common_logger()->is_error_only_mode();
            ?>
            <label>
                <input type="checkbox" name="common_logger_options[error_only_mode]" value="1" <?php checked($enabled); ?> />
                <?php esc_html_e('Enable error-only logging mode', 'common-logger-utility'); ?>
            </label>
            <p class="description">
                <?php esc_html_e('When enabled: Logs only PHP errors, exceptions, fatal errors, and slow queries. When disabled: Logs hook execution flow including where processes stop (hooks with no callbacks).', 'common-logger-utility'); ?>
            </p>
            <?php
        } catch (Throwable $e) {
            echo '<p>' . esc_html__('Error loading setting:', 'common-logger-utility') . ' ' . esc_html($e->getMessage()) . '</p>';
        }
    }

    /**
     * Render hook tracing field
     */
    public function render_hook_tracing_field() {
        $logger = common_logger();
        $enabled = $logger->is_hook_tracing_enabled();
        $currentPrefix = $logger->get_hook_prefix();
        ?>
        <label>
            <input type="checkbox" name="common_logger_options[hook_tracing_enabled]" value="1" <?php checked($enabled); ?> />
            <?php esc_html_e('Enable hook tracing', 'common-logger-utility'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Trace only hooks with this prefix (leave blank to log every hook—use cautiously).', 'common-logger-utility'); ?>
        </p>
        <input type="text" class="regular-text" name="common_logger_options[hook_prefix]" value="<?php echo esc_attr($currentPrefix); ?>" />
        <?php
    }

    /**
     * Render PHP error logging field
     */
    public function render_php_errors_field() {
        $enabled = common_logger()->is_php_error_logging_enabled();
        ?>
        <label>
            <input type="checkbox" name="common_logger_options[php_error_enabled]" value="1" <?php checked($enabled); ?> />
            <?php esc_html_e('Capture PHP warnings, notices, and fatal errors.', 'common-logger-utility'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('These hooks ensure unexpected errors are recorded for later review.', 'common-logger-utility'); ?>
        </p>
        <?php
    }

    /**
     * Render slow query field
     */
    public function render_slow_query_field() {
        $logger = common_logger();
        $enabled = $logger->is_slow_query_logging_enabled();
        $threshold = $logger->get_slow_query_threshold();
        ?>
        <label>
            <input type="checkbox" name="common_logger_options[slow_query_enabled]" value="1" <?php checked($enabled); ?> />
            <?php esc_html_e('Capture slow database queries (requires SAVEQUERIES).', 'common-logger-utility'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Only enable SAVEQUERIES temporarily—it affects performance by storing every query.', 'common-logger-utility'); ?>
        </p>
        <label>
            <?php esc_html_e('Threshold (seconds):', 'common-logger-utility'); ?>
            <input type="number" step="0.1" min="0.1" name="common_logger_options[slow_query_threshold]" value="<?php echo esc_attr($threshold); ?>" />
        </label>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!common_logger_current_user_can_manage()) {
            return;
        }

        // Suppress all notices and warnings on settings page
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(E_ERROR | E_PARSE);

        ?>
        <div class="wrap">
            <div class="common-logger-settings-section">
                <h1><?php esc_html_e('Common Logger Settings', 'common-logger-utility'); ?></h1>
                <p><?php esc_html_e('Configure logging options for your WordPress site.', 'common-logger-utility'); ?></p>

                <form action="options.php" method="post">
                    <?php
                    settings_fields('common_logger_options');
                    do_settings_sections('common_logger_settings');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render tools page with log table
     */
    public function render_tools_page() {
        if (!common_logger_current_user_can_manage()) {
            return;
        }

        // Suppress all notices and warnings on tools page
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(E_ERROR | E_PARSE);

        $logger = common_logger();
        $storage_mode = $logger->get_storage_mode();

        $level_filter = isset($_GET['common_logger_level']) ? sanitize_text_field(wp_unslash($_GET['common_logger_level'])) : '';
        $plugin_filter = isset($_GET['common_logger_plugin']) ? sanitize_text_field(wp_unslash($_GET['common_logger_plugin'])) : '';
        $search_filter = isset($_GET['common_logger_search']) ? sanitize_text_field(wp_unslash($_GET['common_logger_search'])) : '';
        $limit_filter = isset($_GET['common_logger_limit']) ? absint($_GET['common_logger_limit']) : Common_Logger::DEFAULT_LOG_LIMIT;

        $facet_logs = $logger->get_logs(array(
            'limit' => 500,
            'fetch_limit' => 2000,
        ));
        $facet_plugins = array();

        foreach ($facet_logs as $entry) {
            if (!empty($entry['origin_plugin'])) {
                $facet_plugins[$entry['origin_plugin']] = true;
            }
        }

        $per_page = max(1, $limit_filter);
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        if (Common_Logger::STORAGE_DATABASE === $storage_mode) {
            $total_logs = $logger->get_logs_count(array(
                'level' => $level_filter,
                'plugin' => $plugin_filter,
                'search' => $search_filter,
            ));
            $total_pages = ceil($total_logs / $per_page);

            $logs = $logger->get_logs(array(
                'limit' => $per_page,
                'offset' => $offset,
                'level' => $level_filter,
                'plugin' => $plugin_filter,
                'search' => $search_filter,
                'fetch_limit' => 0,
            ));
        } else {
            $logs = $logger->get_logs(array(
                'limit' => $limit_filter,
                'level' => $level_filter,
                'plugin' => $plugin_filter,
                'search' => $search_filter,
                'fetch_limit' => max(4 * $limit_filter, Common_Logger::DEFAULT_LOG_LIMIT * 2),
            ));
        }

        $file_url = Common_Logger::STORAGE_FILE === $storage_mode ? $logger->get_log_file_url() : null;
        ?>

        <div class="wrap">
            <div class="common-logger-tools-header">
                <h1><?php esc_html_e('Common Logger Viewer', 'common-logger-utility'); ?></h1>
                <div class="common-logger-storage-info">
                    <strong><?php esc_html_e('Current storage mode:', 'common-logger-utility'); ?> <?php echo esc_html(ucfirst($storage_mode)); ?></strong>
                </div>
            </div>

            <?php if ($file_url) : ?>
                <p>
                    <a class="button button-secondary" href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Open Log File', 'common-logger-utility'); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if (Common_Logger::STORAGE_DATABASE === $storage_mode) { ?>
                <form method="get" action="<?php echo esc_url(admin_url('tools.php')); ?>" class="common-logger-filters">
                    <input type="hidden" name="page" value="common_logger_tools" />
                    <fieldset>
                        <legend><?php esc_html_e('Filters', 'common-logger-utility'); ?></legend>
                        <label for="common_logger_level">
                            <?php esc_html_e('Level', 'common-logger-utility'); ?>
                            <select name="common_logger_level" id="common_logger_level">
                                <?php
                                $levels = common_logger_get_level_options();
                                foreach ($levels as $value => $label) {
                                    printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($level_filter, $value, false), esc_html($label));
                                }
                                ?>
                            </select>
                        </label>
                        <label for="common_logger_plugin" style="margin-left: 15px;">
                            <?php esc_html_e('Plugin', 'common-logger-utility'); ?>
                            <select name="common_logger_plugin" id="common_logger_plugin">
                                <option value=""><?php esc_html_e('All plugins', 'common-logger-utility'); ?></option>
                                <?php foreach (array_keys($facet_plugins) as $plugin_slug) : ?>
                                    <option value="<?php echo esc_attr($plugin_slug); ?>" <?php selected($plugin_filter, $plugin_slug); ?>>
                                        <?php echo esc_html(common_logger_get_plugin_label($plugin_slug)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label for="common_logger_limit" style="margin-left: 15px;">
                            <?php esc_html_e('Entries', 'common-logger-utility'); ?>
                            <input type="number" min="1" max="2000" name="common_logger_limit" id="common_logger_limit" value="<?php echo esc_attr(max(1, $limit_filter)); ?>" />
                        </label>
                        <label for="common_logger_search" style="margin-left: 15px; display: inline-block; min-width: 220px;">
                            <?php esc_html_e('Search', 'common-logger-utility'); ?>
                            <input type="search" name="common_logger_search" id="common_logger_search" value="<?php echo esc_attr($search_filter); ?>" placeholder="<?php esc_attr_e('Message, file, or context...', 'common-logger-utility'); ?>" />
                        </label>
                        <button type="submit" class="button"><?php esc_html_e('Apply Filters', 'common-logger-utility'); ?></button>
                        <a class="button" href="<?php echo esc_url(common_logger_get_tools_page_url()); ?>"><?php esc_html_e('Reset', 'common-logger-utility'); ?></a>
                    </fieldset>
                </form>

                <?php if (empty($logs)) : ?>
                    <div class="common-logger-no-data">
                        <p><?php esc_html_e('No log entries available.', 'common-logger-utility'); ?></p>
                    </div>
                <?php else : ?>
                    <div class="common-logger-table-container">
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Timestamp', 'common-logger-utility'); ?></th>
                                    <th><?php esc_html_e('Level', 'common-logger-utility'); ?></th>
                                    <th><?php esc_html_e('Plugin', 'common-logger-utility'); ?></th>
                                    <th><?php esc_html_e('Issue', 'common-logger-utility'); ?></th>
                                    <th><?php esc_html_e('Message', 'common-logger-utility'); ?></th>
                                    <th><?php esc_html_e('Context', 'common-logger-utility'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log) :
                                    $plugin_slug = isset($log['origin_plugin']) ? $log['origin_plugin'] : '';
                                    $plugin_label = $plugin_slug ? common_logger_get_plugin_label($plugin_slug) : __('Unknown', 'common-logger-utility');
                                    $origin_file = isset($log['origin_file']) ? $log['origin_file'] : '';
                                    $issue_summary = isset($log['issue_summary']) ? $log['issue_summary'] : '';
                                    $context_array = isset($log['context_array']) && is_array($log['context_array']) ? $log['context_array'] : array();
                                    unset($context_array['_origin_plugin'], $context_array['_origin_file']);

                                    $level = isset($log['level']) ? strtoupper($log['level']) : '';
                                    $level_class = 'common-logger-level-' . strtolower($level);
                                    $level_icon = '';

                                    switch ($level) {
                                        case 'ERROR':
                                            $level_icon = '<span class="dashicons dashicons-warning" style="color: #dc3232;"></span>';
                                            break;
                                        case 'WARNING':
                                            $level_icon = '<span class="dashicons dashicons-flag" style="color: #ffb900;"></span>';
                                            break;
                                        case 'NOTICE':
                                            $level_icon = '<span class="dashicons dashicons-info" style="color: #00a0d2;"></span>';
                                            break;
                                        case 'INFO':
                                            $level_icon = '<span class="dashicons dashicons-admin-generic" style="color: #46b450;"></span>';
                                            break;
                                        case 'DEBUG':
                                            $level_icon = '<span class="dashicons dashicons-admin-tools" style="color: #666;"></span>';
                                            break;
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo esc_html(isset($log['logged_at']) ? $log['logged_at'] : ''); ?></td>
                                        <td>
                                            <span class="common-logger-level-badge <?php echo esc_attr($level_class); ?>">
                                                <?php echo $level_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                <?php echo esc_html($level); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo esc_html($plugin_label); ?>
                                            <?php if ($origin_file) : ?>
                                                <br /><code><?php echo esc_html($origin_file); ?></code>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html($issue_summary ? $issue_summary : __('Not specified', 'common-logger-utility')); ?>
                                        </td>
                                        <td><?php echo esc_html(isset($log['message']) ? $log['message'] : ''); ?></td>
                                        <td>
                                            <?php if (!empty($context_array) || !empty($log['context'])) : ?>
                                                <button class="button button-small common-logger-view-context" data-context="<?php echo esc_attr(wp_json_encode($context_array ?: json_decode($log['context'], true) ?: array())); ?>"><?php esc_html_e('View Details', 'common-logger-utility'); ?></button>
                                            <?php else : ?>
                                                <?php esc_html_e('No context', 'common-logger-utility'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (Common_Logger::STORAGE_DATABASE === $storage_mode && $total_pages > 1) : ?>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <?php
                                $base_url = add_query_arg(array(
                                    'page' => 'common_logger_tools',
                                    'common_logger_level' => $level_filter,
                                    'common_logger_plugin' => $plugin_filter,
                                    'common_logger_search' => $search_filter,
                                    'common_logger_limit' => $limit_filter,
                                ), admin_url('tools.php'));
                                $pagination_args = array(
                                    'base' => $base_url . '%_%',
                                    'format' => '&paged=%#%',
                                    'total' => $total_pages,
                                    'current' => $current_page,
                                    'prev_text' => __('&laquo; Previous', 'common-logger-utility'),
                                    'next_text' => __('Next &raquo;', 'common-logger-utility'),
                                );
                                echo paginate_links($pagination_args);
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 20px; text-align: center;">
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=common_logger_clear_logs'), 'common_logger_clear_logs', '_common_logger_nonce')); ?>" class="button button-danger" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs? This action cannot be undone.', 'common-logger-utility'); ?>');">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Clear All Logs', 'common-logger-utility'); ?>
                        </a>
                    </div>
                <?php endif; ?>

            <?php } else { ?>
                <div class="common-logger-no-data">
                    <p><?php esc_html_e('Table view is only available when using database storage mode. Please switch to database storage in settings to view logs here.', 'common-logger-utility'); ?></p>
                </div>
            <?php } ?>
        </div>

        <!-- Context Modal -->
        <div id="common-logger-context-modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3><?php esc_html_e('Context Details', 'common-logger-utility'); ?></h3>
                <pre id="modal-context-content"></pre>
            </div>
        </div>
        <?php
    }

    /**
     * Handle log clearing
     */
    public function handle_clear_logs() {
        if (!common_logger_current_user_can_manage()) {
            wp_die(esc_html__('Insufficient permissions.', 'common-logger-utility'));
        }

        check_admin_referer('common_logger_clear_logs', '_common_logger_nonce');

        common_logger()->clear_logs();

        // Redirect based on storage mode
        $logger = common_logger();
        if (Common_Logger::STORAGE_DATABASE === $logger->get_storage_mode()) {
            $redirect_url = common_logger_get_tools_page_url();
        } else {
            $redirect_url = admin_url('options-general.php?page=common_logger_settings');
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Render AI features section
     */
    public function render_ai_features_section() {
        echo '<p>' . esc_html__('Configure AI-powered error detection and developer tools.', 'common-logger-utility') . '</p>';
    }

    /**
     * Render AI insights field
     */
    public function render_ai_insights_field() {
        $options = get_option('common_logger_options', array());
        $value = isset($options['enable_ai_insights']) ? $options['enable_ai_insights'] : true;

        echo '<label for="enable_ai_insights">';
        echo '<input type="checkbox" id="enable_ai_insights" name="common_logger_options[enable_ai_insights]" value="1" ' . checked($value, true, false) . ' />';
        echo ' ' . esc_html__('Enable AI-powered error insights and function chain detection', 'common-logger-utility');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Automatically detect plugin, theme, file, hook, and function chain for each error.', 'common-logger-utility') . '</p>';
    }

    /**
     * Render tool integrations field
     */
    public function render_tool_integrations_field() {
        $options = get_option('common_logger_options', array());
        $value = isset($options['enable_tool_integrations']) ? $options['enable_tool_integrations'] : false;

        echo '<label for="enable_tool_integrations">';
        echo '<input type="checkbox" id="enable_tool_integrations" name="common_logger_options[enable_tool_integrations]" value="1" ' . checked($value, true, false) . ' />';
        echo ' ' . esc_html__('Enable optional tool integrations (Whoops, VarDumper, Monolog, Xdebug)', 'common-logger-utility');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Integrate with debugging tools for enhanced error handling and output.', 'common-logger-utility') . '</p>';
    }

    /**
     * Render REST API field
     */
    public function render_rest_api_field() {
        $options = get_option('common_logger_options', array());
        $value = isset($options['enable_rest_api']) ? $options['enable_rest_api'] : true;

        echo '<label for="enable_rest_api">';
        echo '<input type="checkbox" id="enable_rest_api" name="common_logger_options[enable_rest_api]" value="1" ' . checked($value, true, false) . ' />';
        echo ' ' . esc_html__('Enable REST API endpoints', 'common-logger-utility');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Provides API access to logs and insights at /wp-json/common-logger/v1/', 'common-logger-utility') . '</p>';
    }

    /**
     * Render developer mode field
     */
    public function render_developer_mode_field() {
        $options = get_option('common_logger_options', array());
        $value = isset($options['developer_mode']) ? $options['developer_mode'] : false;

        echo '<label for="developer_mode">';
        echo '<input type="checkbox" id="developer_mode" name="common_logger_options[developer_mode]" value="1" ' . checked($value, true, false) . ' />';
        echo ' ' . esc_html__('Enable developer mode', 'common-logger-utility');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Show debug information directly in admin pages for development.', 'common-logger-utility') . '</p>';
    }

    /**
     * Render notification threshold field
     */
    public function render_notification_threshold_field() {
        $options = get_option('common_logger_options', array());
        $value = isset($options['notification_threshold']) ? $options['notification_threshold'] : 10;

        echo '<input type="number" id="notification_threshold" name="common_logger_options[notification_threshold]" value="' . esc_attr($value) . '" min="0" max="1000" />';
        echo '<p class="description">' . esc_html__('Send notifications when error count exceeds this threshold (0 to disable).', 'common-logger-utility') . '</p>';
    }
}
