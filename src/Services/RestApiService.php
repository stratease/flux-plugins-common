<?php
/**
 * REST API service for WordPress REST API route registration.
 *
 * @package FluxPlugins\Common\Services
 * @since 1.0.0
 */

namespace FluxPlugins\Common\Services;

use FluxPlugins\Common\Http\Controllers\LicenseController;
use FluxPlugins\Common\Http\Controllers\LogsController;
use FluxPlugins\Common\Logger\Logger;

/**
 * REST API service.
 *
 * Centralized REST API route registration ensuring routes are registered once,
 * shared across all plugins in the Flux Suite.
 *
 * **Important:** This service uses WordPress action hooks (`did_action()` / `do_action()`)
 * to track registration state per request across all plugin instances. This is necessary because
 * each plugin uses Strauss to namespace-prefix this library, resulting in separate class instances.
 * WordPress action hooks provide the shared state layer that ensures routes only appear once per
 * request, even when multiple plugins with different namespaces call the registration methods.
 *
 * ## Hook Naming Convention
 *
 * All hooks follow the pattern: `{plugin_namespace}/{class_name}/{method_name}`
 *
 * - **Plugin Namespace**: `flux_suite` - Identifies the Flux Plugins suite
 * - **Class Name**: `rest_api_service` - The class name in snake_case
 * - **Method Name**: The method name that fires the hook
 *
 * Examples:
 * - `flux_suite/flux_plugins/hook_rest_routes` - Fired when REST API routes hook is registered
 * - `flux_suite/flux_plugins/register_rest_routes` - Fired when REST API routes are actually registered
 *
 * @since 1.0.0
 */
class RestApiService {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var RestApiService|null
	 */
	private static $instance = null;

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return RestApiService Singleton instance.
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
	 * @param Logger|null $logger Optional logger instance.
	 */
	private function __construct( $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Initialize REST API service.
	 *
	 * Hooks into WordPress 'rest_api_init' action to register routes.
	 * This method ensures routes are only registered once, shared across all plugins.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		// Register REST API routes (only register once, shared across all plugins).
		// Hook directly to rest_api_init to ensure routes are registered early.
		if ( ! did_action( 'flux_suite/flux_plugins/hook_rest_routes' ) ) {
			// Mark as hooked to prevent duplicate hooks (shared across all plugins).
			/**
			 * Fires when REST API routes hook is registered.
			 *
			 * @since 1.0.0
			 */
			do_action( 'flux_suite/flux_plugins/hook_rest_routes' );

			// Use method reference instead of closure to avoid namespace issues with Strauss.
			add_action( 'rest_api_init', [ $this, 'register_rest_routes' ], 10 );
		}
	}

	/**
	 * Register REST API routes.
	 *
	 * Called by WordPress rest_api_init hook. Registers routes only once,
	 * shared across all plugins.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_rest_routes() {
		// Only register once per request (shared across all plugins).
		if ( did_action( 'flux_suite/flux_plugins/register_rest_routes' ) ) {
			return;
		}

		// Get logger instance (uses plugin slug set during FluxPlugins::init()).
		$logger = Logger::get_instance();

		// Register License controller routes.
		$license_controller = new LicenseController( $logger );
		$license_controller->register_routes();

		// Register Logs controller routes.
		$logs_controller = new LogsController( $logger );
		$logs_controller->register_routes();

		/**
		 * Fires when REST API routes are actually registered.
		 *
		 * Allows other plugins or code to register additional routes.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/flux_plugins/register_rest_routes' );
	}
}


