<?php
/**
 * Common Logger Performance Monitor
 *
 * Hooks into WordPress runtime to capture slow HTTP requests, AJAX calls,
 * REST responses, and other long running processes.
 *
 * @package CommonLogger
 * @version 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance monitor singleton.
 */
class Common_Logger_Monitor {
    /**
     * Singleton instance.
     *
     * @var Common_Logger_Monitor|null
     */
    private static $instance = null;

    /**
     * Request start time.
     *
     * @var float
     */
    private $request_start = 0.0;

    /**
     * HTTP request threshold (seconds).
     *
     * @var float
     */
    private $http_threshold = 1.5;

    /**
     * AJAX/REST request threshold (seconds).
     *
     * @var float
     */
    private $ajax_threshold = 1.0;

    /**
     * Whether hooks are registered.
     *
     * @var bool
     */
    private $bootstrapped = false;

    /**
     * Retrieve singleton instance.
     *
     * @return Common_Logger_Monitor
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Bootstrap monitoring hooks.
     */
    public function bootstrap() {
        if ($this->bootstrapped) {
            return;
        }

        $this->bootstrapped = true;
        $this->request_start = microtime(true);
        $this->http_threshold = (float) apply_filters('common_logger_http_threshold', 1.5);
        $this->ajax_threshold = (float) apply_filters('common_logger_ajax_threshold', 1.0);

        add_filter('http_request_args', array($this, 'tag_http_request'), 10, 2);
        add_filter('http_response', array($this, 'inspect_http_response'), 10, 3);
        add_action('shutdown', array($this, 'analyze_request_duration'), PHP_INT_MAX);
    }

    /**
     * Tag outgoing HTTP request with start timestamp.
     *
     * @param array  $args Request arguments.
     * @param string $url  Request URL.
     * @return array
     */
    public function tag_http_request($args, $url) {
        $args['_common_logger_start'] = microtime(true);
        return $args;
    }

    /**
     * Inspect HTTP response for latency issues.
     *
     * @param array|WP_Error $response Response object.
     * @param array          $args     Request arguments.
     * @param string         $url      Request URL.
     * @return array|WP_Error
     */
    public function inspect_http_response($response, $args, $url) {
        if (empty($args['_common_logger_start'])) {
            return $response;
        }

        $duration = microtime(true) - (float) $args['_common_logger_start'];

        if ($this->http_threshold > 0 && $duration >= $this->http_threshold) {
            $context = array(
                'url'       => $url,
                'duration'  => round($duration, 4),
                'threshold' => $this->http_threshold,
                'method'    => isset($args['method']) ? strtoupper($args['method']) : 'GET',
            );

            if (is_wp_error($response)) {
                $context['error'] = $response->get_error_message();
                common_logger()->error('Slow HTTP API request (error)', $context);
            } else {
                $context['response_code'] = isset($response['response']['code']) ? (int) $response['response']['code'] : null;
                common_logger()->warning('Slow HTTP API request detected', $context);
            }
        }

        return $response;
    }

    /**
     * Inspect entire request duration for AJAX/REST latency.
     */
    public function analyze_request_duration() {
        $duration = microtime(true) - $this->request_start;

        if ($this->ajax_threshold <= 0 || $duration < $this->ajax_threshold) {
            return;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (function_exists('wp_unslash')) {
            $uri = wp_unslash($uri);
        }
        if (function_exists('esc_url_raw')) {
            $uri = esc_url_raw($uri);
        }

        $context = array(
            'duration'  => round($duration, 4),
            'threshold' => $this->ajax_threshold,
            'uri'       => $uri,
        );

        if (wp_doing_ajax()) {
            $ajax_action = isset($_REQUEST['action']) ? $_REQUEST['action'] : ''; // phpcs:ignore WordPress.Security.NonceVerification
            if (function_exists('wp_unslash')) {
                $ajax_action = wp_unslash($ajax_action);
            }
            if (function_exists('sanitize_key')) {
                $ajax_action = sanitize_key($ajax_action);
            }
            $context['ajax_action'] = $ajax_action;
            common_logger()->warning('Slow AJAX request detected', $context);
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $rest_route = isset($GLOBALS['wp']->query_vars['rest_route']) ? $GLOBALS['wp']->query_vars['rest_route'] : '';
            if (function_exists('sanitize_text_field')) {
                $rest_route = sanitize_text_field($rest_route);
            }
            $context['rest_route'] = $rest_route;
            common_logger()->warning('Slow REST request detected', $context);
            return;
        }

        // Optionally report slow front-end or admin requests.
        if (!is_admin()) {
            common_logger()->notice('Slow front-end request detected', $context);
        } else {
            common_logger()->notice('Slow admin request detected', $context);
        }
    }
}
