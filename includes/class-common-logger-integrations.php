<?php
/**
 * Common Logger Integrations Class
 *
 * Handles optional integrations with debugging tools.
 *
 * @package CommonLogger
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Common Logger Integrations Class
 */
class Common_Logger_Integrations {

    /**
     * Singleton instance
     *
     * @var Common_Logger_Integrations|null
     */
    private static $instance = null;

    /**
     * Available integrations
     *
     * @var array
     */
    private $available_integrations = array(
        'whoops' => 'Whoops\Run',
        'var_dumper' => 'Symfony\Component\VarDumper\VarDumper',
        'monolog' => 'Monolog\Logger',
        'xdebug' => false, // Xdebug is a PHP extension, not a class
    );

    /**
     * Loaded integrations
     *
     * @var array
     */
    private $loaded_integrations = array();

    /**
     * Get singleton instance
     *
     * @return Common_Logger_Integrations
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
     * Initialize integrations
     */
    public function init() {
        $this->load_integrations();
        $this->setup_integrations();
    }

    /**
     * Load available integrations
     */
    private function load_integrations() {
        foreach ($this->available_integrations as $name => $class) {
            if ($this->is_integration_available($name, $class)) {
                $this->loaded_integrations[$name] = true;
                $this->init_integration($name);
            }
        }
    }

    /**
     * Check if integration is available
     *
     * @param string $name Integration name
     * @param string|false $class Class name or false for extensions
     * @return bool
     */
    private function is_integration_available($name, $class) {
        if ($class === false) {
            // Check for PHP extension
            return extension_loaded($name);
        }

        // Check for class existence
        return class_exists($class, true);
    }

    /**
     * Initialize specific integration
     *
     * @param string $name Integration name
     */
    private function init_integration($name) {
        switch ($name) {
            case 'whoops':
                $this->init_whoops();
                break;

            case 'var_dumper':
                $this->init_var_dumper();
                break;

            case 'monolog':
                $this->init_monolog();
                break;

            case 'xdebug':
                $this->init_xdebug();
                break;
        }
    }

    /**
     * Setup integrations
     */
    private function setup_integrations() {
        // Add integration info to log context
        add_filter('common_logger_pre_log', array($this, 'add_integration_context'), 10, 2);
    }

    /**
     * Initialize Whoops error handler
     */
    private function init_whoops() {
        try {
            $whoops = new Whoops\Run();

            // Add Common Logger handler
            $whoops->pushHandler(array($this, 'whoops_handler'));

            // Only register if not already registered
            if (Whoops\Run::isRegistered()) {
                Whoops\Run::setInstance($whoops);
            } else {
                $whoops->register();
            }
        } catch (Exception $e) {
            common_logger()->warning('Failed to initialize Whoops integration: ' . $e->getMessage());
        }
    }

    /**
     * Whoops error handler
     *
     * @param Whoops\Exception\ErrorException $exception
     */
    public function whoops_handler($exception) {
        $context = array(
            'whoops_exception' => true,
            'exception_class' => get_class($exception),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'exception_trace' => $exception->getTraceAsString(),
            'function_chain' => common_logger_build_function_chain(),
        );

        common_logger()->error($exception->getMessage(), $context);
    }

    /**
     * Initialize Symfony VarDumper
     */
    private function init_var_dumper() {
        try {
            // Set up custom dumper that integrates with Common Logger
            Symfony\Component\VarDumper\VarDumper::setHandler(array($this, 'var_dumper_handler'));
        } catch (Exception $e) {
            common_logger()->warning('Failed to initialize VarDumper integration: ' . $e->getMessage());
        }
    }

    /**
     * VarDumper handler
     *
     * @param mixed $var Variable to dump
     */
    public function var_dumper_handler($var) {
        $context = array(
            'var_dumper_output' => true,
            'dumped_variable' => $this->var_to_string($var),
            'function_chain' => common_logger_build_function_chain(),
        );

        common_logger()->debug('Variable dump', $context);
    }

    /**
     * Convert variable to string representation
     *
     * @param mixed $var Variable
     * @return string
     */
    private function var_to_string($var) {
        if (is_scalar($var)) {
            return (string) $var;
        }

        if (is_array($var)) {
            return 'Array(' . count($var) . ')';
        }

        if (is_object($var)) {
            return 'Object(' . get_class($var) . ')';
        }

        return gettype($var);
    }

    /**
     * Initialize Monolog
     */
    private function init_monolog() {
        try {
            // Create Monolog logger that bridges to Common Logger
            $this->monolog_logger = new Monolog\Logger('common-logger');

            // Add Common Logger handler
            $this->monolog_logger->pushHandler(array($this, 'monolog_handler'));
        } catch (Exception $e) {
            common_logger()->warning('Failed to initialize Monolog integration: ' . $e->getMessage());
        }
    }

    /**
     * Monolog handler
     *
     * @param array $record Log record
     */
    public function monolog_handler($record) {
        $level_map = array(
            Monolog\Logger::DEBUG => 'DEBUG',
            Monolog\Logger::INFO => 'INFO',
            Monolog\Logger::NOTICE => 'NOTICE',
            Monolog\Logger::WARNING => 'WARNING',
            Monolog\Logger::ERROR => 'ERROR',
            Monolog\Logger::CRITICAL => 'ERROR',
            Monolog\Logger::ALERT => 'ERROR',
            Monolog\Logger::EMERGENCY => 'ERROR',
        );

        $level = isset($level_map[$record['level']]) ? $level_map[$record['level']] : 'INFO';

        $context = array(
            'monolog_record' => true,
            'monolog_channel' => $record['channel'],
            'monolog_extra' => isset($record['extra']) ? $record['extra'] : array(),
            'function_chain' => common_logger_build_function_chain(),
        );

        common_logger()->log($record['message'], $level, $context);
    }

    /**
     * Initialize Xdebug integration
     */
    private function init_xdebug() {
        // Xdebug is already loaded as a PHP extension
        // We can enhance stack traces and profiling if needed

        if (function_exists('xdebug_get_function_stack')) {
            add_filter('common_logger_pre_log', array($this, 'add_xdebug_context'), 10, 2);
        }
    }

    /**
     * Add integration context to log
     *
     * @param string $message Log message
     * @param array $context Log context
     * @return array
     */
    public function add_integration_context($message, $context) {
        $context['_integrations_loaded'] = array_keys($this->loaded_integrations);
        return array($message, $context);
    }

    /**
     * Add Xdebug context to log
     *
     * @param string $message Log message
     * @param array $context Log context
     * @return array
     */
    public function add_xdebug_context($message, $context) {
        if (function_exists('xdebug_get_function_stack')) {
            $stack = xdebug_get_function_stack();
            if (!empty($stack)) {
                $context['_xdebug_stack'] = array_slice($stack, 0, 10); // Limit stack depth
            }
        }

        return array($message, $context);
    }

    /**
     * Get loaded integrations
     *
     * @return array
     */
    public function get_loaded_integrations() {
        return array_keys($this->loaded_integrations);
    }

    /**
     * Check if specific integration is loaded
     *
     * @param string $name Integration name
     * @return bool
     */
    public function is_integration_loaded($name) {
        return isset($this->loaded_integrations[$name]);
    }

    /**
     * Get Monolog logger instance (if available)
     *
     * @return Monolog\Logger|null
     */
    public function get_monolog_logger() {
        return isset($this->monolog_logger) ? $this->monolog_logger : null;
    }
}