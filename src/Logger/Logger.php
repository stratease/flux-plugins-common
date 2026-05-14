<?php
/**
 * Logger utility class with structured logging support.
 *
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * @package FluxPlugins\Common\Logger
 * @since 1.0.0
 * @since 1.0.0 Added externally managed source notice.
 * @since 1.2.0 Removed Monolog and PSR-3 interface; internal persistence plus error_log for high severity.
 */

namespace FluxPlugins\Common\Logger;

/**
 * Suite logger: database rows for admin Logs UI plus server error_log for high-severity entries.
 *
 * Method names match common PSR-3 usage; this class does not implement {@see \Psr\Log\LoggerInterface}.
 *
 * @since 1.0.0
 */
class Logger {

	/**
	 * Maximum length for JSON context appended to error_log lines.
	 *
	 * @since 1.2.0
	 */
	private const ERROR_LOG_CONTEXT_MAX_BYTES = 1024;

	/**
	 * Canonical uppercase level names for ordering and validation.
	 *
	 * @since 1.2.0
	 */
	private const LEVEL_ORDER = [
		'EMERGENCY' => 800,
		'ALERT'     => 700,
		'CRITICAL'  => 600,
		'ERROR'     => 500,
		'WARNING'   => 400,
		'NOTICE'    => 300,
		'INFO'      => 200,
		'DEBUG'     => 100,
	];

	/**
	 * Levels echoed to PHP error_log (host log), not file.
	 *
	 * @since 1.2.0
	 * @var string[]
	 */
	private const ERROR_LOG_LEVELS = [ 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' ];

	/**
	 * Legacy Monolog 2 level integers for {@see log()} callers.
	 *
	 * @since 1.2.0
	 * @var array<int, string>
	 */
	private const MONOLOG_INT_TO_NAME = [
		100 => 'DEBUG',
		200 => 'INFO',
		250 => 'NOTICE',
		300 => 'WARNING',
		400 => 'ERROR',
		500 => 'CRITICAL',
		550 => 'ALERT',
		600 => 'EMERGENCY',
	];

	/**
	 * Lazy database writer; null until first persisted row while logging enabled.
	 *
	 * @since 1.2.0
	 * @var DatabaseHandler|null
	 */
	private $db_handler;

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
	 * Optional sink for error_log-style output (PHPUnit).
	 *
	 * @since 1.2.0
	 * @var callable|null
	 */
	private static $error_log_sink = null;

	/**
	 * Replace PHP error_log for tests; pass null to restore default behavior.
	 *
	 * For automated tests only; do not use in production plugins.
	 *
	 * @since 1.2.0
	 *
	 * @param callable|null $sink Receives a single string line.
	 * @return void
	 */
	public static function set_error_log_sink_for_tests( ?callable $sink ): void {
		self::$error_log_sink = $sink;
	}

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
		if ( self::$instance === null ) {
			self::$instance = new self();
		} else {
			self::$instance->plugin_slug = $plugin_slug;
			self::$instance->db_handler   = null;
		}
	}

	/**
	 * Get singleton instance.
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
		$this->db_handler    = null;
	}

	/**
	 * Whether suite database logging is enabled.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	private function is_site_logging_enabled() {
		$options = get_site_option( 'flux-plugins_common_options', [] );

		return (bool) ( $options['enable_logging'] ?? true );
	}

	/**
	 * @since 1.2.0
	 * @return DatabaseHandler
	 */
	private function get_db_handler() {
		if ( $this->db_handler === null ) {
			$this->db_handler = new DatabaseHandler( $this->plugin_slug );
		}

		return $this->db_handler;
	}

	/**
	 * @since 1.2.0
	 * @param string               $level_name Uppercase level name.
	 * @param string               $message    Message.
	 * @param array                $context    Context.
	 * @param \DateTimeInterface|null $datetime Optional.
	 * @return void
	 */
	private function dispatch( $level_name, $message, array $context, $datetime = null ) {
		$level_name = strtoupper( (string) $level_name );
		if ( ! isset( self::LEVEL_ORDER[ $level_name ] ) ) {
			$level_name = 'INFO';
		}

		$this->maybe_error_log( $level_name, (string) $message, $context );

		if ( ! $this->is_site_logging_enabled() ) {
			return;
		}

		$this->get_db_handler()->persist( $level_name, (string) $message, $context, $datetime );
	}

	/**
	 * @since 1.2.0
	 * @param string $level_name Uppercase PSR-like level.
	 * @param string $message    Message.
	 * @param array  $context    Context.
	 * @return void
	 */
	private function maybe_error_log( $level_name, $message, array $context ) {
		if ( ! in_array( $level_name, self::ERROR_LOG_LEVELS, true ) ) {
			return;
		}

		$line = sprintf( '[%s][%s] %s', $this->plugin_slug, $level_name, $message );
		if ( ! empty( $context ) ) {
			$ctx = wp_json_encode( $context );
			if ( false === $ctx ) {
				$ctx = '{}';
			}
			if ( strlen( $ctx ) > self::ERROR_LOG_CONTEXT_MAX_BYTES ) {
				$ctx = substr( $ctx, 0, self::ERROR_LOG_CONTEXT_MAX_BYTES - 3 ) . '...';
			}
			$line .= ' ' . $ctx;
		}

		if ( self::$error_log_sink !== null ) {
			( self::$error_log_sink )( $line );

			return;
		}

		error_log( $line );
	}

	/**
	 * Normalize arbitrary level input (PSR-3 names or legacy Monolog integers).
	 *
	 * @since 1.2.0
	 * @param mixed $level Level.
	 * @return string Uppercase level name.
	 */
	private function normalize_level_name( $level ) {
		if ( is_int( $level ) || ( is_string( $level ) && ctype_digit( (string) $level ) ) ) {
			$int_level = (int) $level;
			if ( isset( self::MONOLOG_INT_TO_NAME[ $int_level ] ) ) {
				return self::MONOLOG_INT_TO_NAME[ $int_level ];
			}

			return 'INFO';
		}

		if ( ! is_string( $level ) ) {
			return 'INFO';
		}

		$name = strtoupper( $level );
		if ( isset( self::LEVEL_ORDER[ $name ] ) ) {
			return $name;
		}

		return 'INFO';
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
		$this->dispatch( 'DEBUG', $message, $context );
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
		$this->dispatch( 'INFO', $message, $context );
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
		$this->dispatch( 'NOTICE', $message, $context );
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
		$this->dispatch( 'WARNING', $message, $context );
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
		$this->dispatch( 'ERROR', $message, $context );
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
		$this->dispatch( 'CRITICAL', $message, $context );
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
		$this->dispatch( 'ALERT', $message, $context );
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
		$this->dispatch( 'EMERGENCY', $message, $context );
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
		$this->dispatch( $this->normalize_level_name( $level ), $message, $context );
	}
}
