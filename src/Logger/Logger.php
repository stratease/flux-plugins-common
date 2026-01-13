<?php
/**
 * Logger utility class with structured logging support.
 *
 * @package FluxPlugins\Common\Logger
 * @since 1.0.0
 */

namespace FluxPlugins\Common\Logger;

use Psr\Log\LoggerInterface;

/**
 * Logger utility class using Monolog with simplified structured logging.
 *
 * The plugin slug is set during FluxPlugins::init() via the init() method.
 * All instances (Strauss namespaced) will use the same plugin slug for this request.
 *
 * @since 1.0.0
 */
class Logger implements LoggerInterface {

	/**
	 * Monolog logger instance.
	 *
	 * @since 1.0.0
	 * @var \Monolog\Logger
	 */
	private $logger;

	/**
	 * Plugin slug for this logger instance.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var Logger|null
	 */
	private static $instance = null;

	/**
	 * Plugin slug set during FluxPlugins::init().
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private static $static_plugin_slug = 'flux-plugins-common';

	/**
	 * Initialize logger with plugin slug.
	 *
	 * Called by FluxPlugins::init() to initialize the logger with the plugin slug.
	 * This must be called before get_instance() is used.
	 *
	 * @since 1.0.0
	 * @param string $plugin_slug Plugin slug.
	 * @return void
	 */
	public static function init( $plugin_slug ) {
		self::$static_plugin_slug = $plugin_slug;
		// Initialize instance if not already created
		if ( self::$instance === null ) {
			self::$instance = new self();
		} else {
			// If instance exists but slug changed, update it
			self::$instance->plugin_slug = $plugin_slug;
			// Reinitialize handlers with new slug
			self::$instance->logger = new \Monolog\Logger( $plugin_slug );
			self::$instance->setup_handlers();
		}
	}

	/**
	 * Get singleton instance.
	 *
	 * Uses the plugin slug set via init() during FluxPlugins::init().
	 *
	 * @since 1.0.0
	 * @return Logger Logger instance.
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->plugin_slug = self::$static_plugin_slug;
		$this->logger = new \Monolog\Logger( $this->plugin_slug );
		$this->setup_handlers();
	}

	/**
	 * Setup log handlers.
	 *
	 * @since 1.0.0
	 */
	private function setup_handlers() {
		// Check if logging is disabled via common library option
		$options = get_site_option( 'flux-plugins_common_options', [] );
		$logging_enabled = $options['enable_logging'] ?? true;
		
		if ( ! $logging_enabled ) {
			// If logging is disabled, don't add any handlers
			return;
		}

		// Database handler for all log levels (DEBUG and above)
		$database_handler = new DatabaseHandler( $this->plugin_slug, \Monolog\Logger::DEBUG );
		$this->logger->pushHandler( $database_handler );
	}

	/**
	 * Log debug message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function debug( $message, array $context = [] ): void {
		$this->logger->debug( $message, $context );
	}

	/**
	 * Log info message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function info( $message, array $context = [] ): void {
		$this->logger->info( $message, $context );
	}

	/**
	 * Log notice message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function notice( $message, array $context = [] ): void {
		$this->logger->notice( $message, $context );
	}

	/**
	 * Log warning message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function warning( $message, array $context = [] ): void {
		$this->logger->warning( $message, $context );
	}

	/**
	 * Log error message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function error( $message, array $context = [] ): void {
		$this->logger->error( $message, $context );
	}

	/**
	 * Log critical message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function critical( $message, array $context = [] ): void {
		$this->logger->critical( $message, $context );
	}

	/**
	 * Log an alert message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function alert( $message, array $context = [] ): void {
		$this->logger->alert( $message, $context );
	}

	/**
	 * Log an emergency message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function emergency( $message, array $context = [] ): void {
		$this->logger->emergency( $message, $context );
	}

	/**
	 * Log with a specific level.
	 *
	 * @since 1.0.0
	 * @param mixed  $level   Log level (string or int).
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function log( $level, $message, array $context = [] ): void {
		$this->logger->log( $level, $message, $context );
	}
}
