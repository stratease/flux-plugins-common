<?php
/**
 * Compatibility service for Flux Plugins suite.
 *
 * @package FluxPlugins\Common\Services
 * @since 1.0.0
 */

namespace FluxPlugins\Common\Services;

use FluxPlugins\Common\Api\ExternalApiClient;
use FluxPlugins\Common\Compatibility\CompatibilityValidator;
use FluxPlugins\Common\Compatibility\CompatibilityNoticeHandler;
use FluxPlugins\Common\Logger\Logger;
use FluxPlugins\Common\Services\I18n;

/**
 * Compatibility service.
 *
 * Manages compatibility validation and notice handling for a single plugin instance.
 * Each plugin instance is namespaced via Strauss, so this service handles one plugin.
 *
 * **Important:** This service uses WordPress action hooks (`did_action()` / `do_action()`)
 * to track asset enqueuing state per request across all plugin instances. This ensures
 * compatibility assets (JavaScript) are only enqueued once, even when multiple plugins
 * use the shared library.
 *
 * ## Hook Naming Convention
 *
 * All hooks follow the pattern: `{plugin_namespace}/{class_name}/{method_name}`
 *
 * - **Plugin Namespace**: `flux_suite` - Identifies the Flux Plugins suite
 * - **Class Name**: `compatibility_service` - The class name in snake_case
 * - **Method Name**: The method name that fires the hook
 *
 * Examples:
 * - `flux_suite/compatibility_service/assets_enqueued` - Fired when compatibility assets are enqueued
 *
 * @since 1.0.0
 */
class CompatibilityService {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var CompatibilityService|null
	 */
	private static $instance = null;

	/**
	 * Plugin slug.
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	private $plugin_slug = null;

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	private $plugin_version = null;

	/**
	 * Common assets URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $common_assets_url = '';

	/**
	 * Compatibility validator instance.
	 *
	 * @since 1.0.0
	 * @var CompatibilityValidator|null
	 */
	private $validator = null;

	/**
	 * Notice handler instance.
	 *
	 * @since 1.0.0
	 * @var CompatibilityNoticeHandler|null
	 */
	private $notice_handler = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return CompatibilityService Singleton instance.
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
		// Private constructor for singleton pattern.
	}

	/**
	 * Initialize compatibility system for a plugin.
	 *
	 * Registers the plugin for deferred initialization. Actual setup happens
	 * on WordPress 'init' and 'admin_init' hooks to ensure proper timing.
	 *
	 * @since 1.0.0
	 * @param string $plugin_slug       Plugin slug (e.g., 'flux-media-optimizer').
	 * @param string $plugin_version    Plugin version (e.g., '1.0.0').
	 * @param string $common_assets_url  URL path to plugin's common assets folder (e.g., plugin_dir_url(__FILE__) . 'src/assets/common/').
	 * @return void
	 */
	public function init_plugin( $plugin_slug, $plugin_version, $common_assets_url = '' ) {
		// Store plugin data.
		$this->plugin_slug       = $plugin_slug;
		$this->plugin_version    = $plugin_version;
		$this->common_assets_url = $common_assets_url;

		// Hook into WordPress 'init' action for validator initialization.
		add_action( 'init', [ $this, 'do_init' ], 10 );

		// Hook into WordPress 'admin_init' action for notice handler initialization.
		add_action( 'admin_init', [ $this, 'do_admin_init' ], 10 );
	}

	/**
	 * Ensure validator is initialized.
	 *
	 * Initializes the compatibility validator if it hasn't been initialized yet.
	 * This can be called directly to ensure the validator is available during
	 * WP cron, REST requests, or other contexts where 'init' may not have fired.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function ensure_validator_initialized() {
		// Skip if already initialized.
		if ( $this->validator !== null ) {
			return;
		}

		// Initialize Logger instance (already initialized with plugin slug in FluxPlugins::init()).
		$logger = Logger::get_instance();

		// Create shared external API client for compatibility checks.
		// Shared API client will use constants internally (FLUX_PLUGINS_COMMON_*).
		$shared_api_client = new ExternalApiClient( $logger );

		// Initialize compatibility validator with dependencies.
		// Cache option name is generated internally by CompatibilityValidator.
		$this->validator = new CompatibilityValidator(
			$logger,
			$shared_api_client,
			$this->plugin_slug,
			$this->plugin_version
		);

		// Invalidate cache on version change.
		$this->validator->invalidate_on_version_change();
	}

	/**
	 * Perform initialization on 'init' hook.
	 *
	 * Initializes compatibility validator for this plugin instance.
	 * This runs after WordPress core is loaded and translations are available.
	 * Uses ensure_validator_initialized() to handle the actual initialization logic.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function do_init() {
		$this->ensure_validator_initialized();
	}

	/**
	 * Perform admin initialization on 'admin_init' hook.
	 *
	 * Initializes notice handler and enqueues assets for this plugin instance.
	 * This runs in admin context only, after WordPress admin is ready.
	 * Handles only admin-specific concerns (notices and assets).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function do_admin_init() {
		// Skip if notice handler already initialized.
		if ( $this->notice_handler !== null ) {
			return;
		}

		// Ensure validator is initialized (for admin notices).
		$this->ensure_validator_initialized();
		
		// If validator still doesn't exist, we can't show notices.
		if ( $this->validator === null ) {
			return;
		}

		// Generate namespaced AJAX action and transient prefix based on plugin slug.
		$ajax_action = 'flux_plugins_compatibility_dismiss_' . sanitize_key( $this->plugin_slug );
		$dismissal_transient_prefix = 'flux_plugins_compatibility_dismissed_' . sanitize_key( $this->plugin_slug ) . '_';

		$this->notice_handler = new CompatibilityNoticeHandler(
			$this->validator,
			$this->plugin_version,
			$dismissal_transient_prefix,
			$ajax_action
		);
		$this->notice_handler->init();

		// Enqueue compatibility assets.
		$this->enqueue_assets();
	}

	/**
	 * Enqueue compatibility assets.
	 *
	 * Enqueues the compatibility dismiss JavaScript from the shared library.
	 * Uses WordPress action hooks to ensure assets are only enqueued once per request,
	 * even when multiple plugins use the shared library.
	 *
	 * The JavaScript is generic and handles all compatibility notices via:
	 * - Generic class selectors (`.flux-plugins-compatibility-notice`, `.flux-plugins-dismiss`)
	 * - Unique data attributes per notice (`data-dismiss-url`, `data-hash`) generated by CompatibilityNoticeHandler
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function enqueue_assets() {
		// Ensure assets are only enqueued once per request (shared across all plugins).
		if ( did_action( 'flux_suite/compatibility_service/enqueue_assets' ) ) {
			return;
		}

		// Hook into admin_enqueue_scripts to enqueue the compatibility dismiss script.
		add_action( 'admin_enqueue_scripts', function() {
			// Use provided common assets URL if available.
			if ( ! empty( $this->common_assets_url ) ) {
				$shared_lib_url = $this->common_assets_url;
			} else {
				// Fallback to vendor-prefixed path for backwards compatibility.
				// This file is at: vendor-prefixed/stratease/flux-plugins-common/src/Services/CompatibilityService.php
				// Assets are at: vendor-prefixed/stratease/flux-plugins-common/src/assets/
				$shared_lib_dir = dirname( dirname( __DIR__ ) );
				$shared_lib_url = plugins_url( '', $shared_lib_dir . '/src/assets' );
			}

			// Determine script path based on SCRIPT_DEBUG (development vs production).
			// Path is relative to the assets directory (which $shared_lib_url points to).
			$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
			$script_path  = $script_debug
				? 'js/src/admin/compatibility-dismiss.js' // Source file for development.
				: 'js/dist/compatibility-dismiss.bundle.js'; // Built file for production.

			// Enqueue script with jQuery as dependency (WordPress Plugin Guidelines #13).
			wp_enqueue_script(
				'flux-plugins-common-compatibility-dismiss',
				trailingslashit( $shared_lib_url ) . $script_path,
				[ 'jquery' ], // jQuery dependency - WordPress default library.
				$this->plugin_version,
				true // Load in footer.
			);
		}, 10 );

		// Mark as enqueued using WordPress action hook (shared across all plugins).
		/**
		 * Fires when compatibility assets are enqueued.
		 *
		 * This action is fired once per request after compatibility assets are successfully enqueued.
		 * It can be used by other code to detect when asset enqueuing has completed.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/compatibility_service/enqueue_assets' );
	}

	/**
	 * Get compatibility validator instance.
	 *
	 * Ensures validator is initialized before returning it.
	 * This allows the validator to be available during WP cron, REST requests,
	 * and other contexts where the 'init' hook may not have fired yet.
	 *
	 * @since 1.0.0
	 * @return CompatibilityValidator|null Compatibility validator instance or null if plugin not initialized.
	 */
	public static function get_validator() {
		$instance = self::get_instance();
		
		// Ensure validator is initialized if plugin data is available.
		if ( $instance->validator === null && $instance->plugin_slug !== null ) {
			$instance->ensure_validator_initialized();
		}
		
		return $instance->validator;
	}

	/**
	 * Get notice handler instance.
	 *
	 * @since 1.0.0
	 * @return CompatibilityNoticeHandler|null Notice handler instance or null if not initialized.
	 */
	public static function get_notice_handler() {
		$instance = self::get_instance();
		return $instance->notice_handler;
	}
}

