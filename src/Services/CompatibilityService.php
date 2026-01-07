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
	 * @param string $plugin_slug    Plugin slug (e.g., 'flux-media-optimizer').
	 * @param string $plugin_version Plugin version (e.g., '1.0.0').
	 * @return void
	 */
	public function init_plugin( $plugin_slug, $plugin_version ) {
		// Store plugin data.
		$this->plugin_slug    = $plugin_slug;
		$this->plugin_version = $plugin_version;

		// Hook into WordPress 'init' action for validator initialization.
		add_action( 'init', [ $this, 'do_init' ], 10 );

		// Hook into WordPress 'admin_init' action for notice handler initialization.
		add_action( 'admin_init', [ $this, 'do_admin_init' ], 10 );
	}

	/**
	 * Perform initialization on 'init' hook.
	 *
	 * Initializes compatibility validator for this plugin instance.
	 * This runs after WordPress core is loaded and translations are available.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function do_init() {
		// Skip if already initialized.
		if ( $this->validator !== null ) {
			return;
		}

		// TODO: Initialize Logger with plugin slug when Logger service is available.
		// For now, compatibility validation will work without logging.
		$logger = null;

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
	 * Perform admin initialization on 'admin_init' hook.
	 *
	 * Initializes notice handler and enqueues assets for this plugin instance.
	 * This runs in admin context only, after WordPress admin is ready.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function do_admin_init() {
		// Skip if notice handler already initialized.
		if ( $this->notice_handler !== null ) {
			return;
		}

		// Ensure validator exists (should have been created in do_init()).
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
			// Get the shared library directory path relative to this file.
			// This file is at: vendor-prefixed/stratease/flux-plugins-common/src/Services/CompatibilityService.php
			// Assets are at: vendor-prefixed/stratease/flux-plugins-common/assets/
			$shared_lib_dir = dirname( dirname( __DIR__ ) );
			$shared_lib_url = plugins_url( '', $shared_lib_dir . '/assets' );

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
	 * @since 1.0.0
	 * @return CompatibilityValidator|null Compatibility validator instance or null if not initialized.
	 */
	public static function get_validator() {
		$instance = self::get_instance();
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

