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

/**
 * Compatibility service.
 *
 * Manages compatibility validation and notice handling per plugin.
 * Each plugin gets its own validator and notice handler, ensuring notices
 * from multiple plugins can display independently.
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
	 * Compatibility validators per plugin.
	 *
	 * @since 1.0.0
	 * @var CompatibilityValidator[]
	 */
	private static $validators = [];

	/**
	 * Notice handlers per plugin.
	 *
	 * @since 1.0.0
	 * @var CompatibilityNoticeHandler[]
	 */
	private static $notice_handlers = [];

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
	 * Creates and initializes CompatibilityValidator and CompatibilityNoticeHandler
	 * for the specified plugin. Each plugin gets its own validator and notice handler,
	 * ensuring notices from multiple plugins can display independently.
	 *
	 * @since 1.0.0
	 * @param string      $plugin_slug    Plugin slug (e.g., 'flux-media-optimizer').
	 * @param string      $plugin_version Plugin version (e.g., '1.0.0').
	 * @param string|null $plugin_url     Optional plugin URL. If not provided, will be generated from plugin slug.
	 * @param string|null $text_domain    Optional text domain. If not provided, will be generated from plugin slug.
	 * @param object|null $logger         Optional logger instance. If not provided, compatibility will work without logging.
	 * @return void
	 */
	public function init_plugin( $plugin_slug, $plugin_version, $plugin_url = null, $text_domain = null, $logger = null ) {
		// Store as current plugin slug for convenience access.
		self::$current_plugin_slug = $plugin_slug;

		// Skip if already initialized for this plugin.
		if ( isset( self::$validators[ $plugin_slug ] ) ) {
			return;
		}

		// Generate plugin URL from plugin slug if not provided.
		// Plugins should provide their URL via filter for accurate asset paths.
		if ( $plugin_url === null ) {
			$plugin_url = apply_filters(
				'flux_plugins_common_plugin_url',
				null,
				$plugin_slug
			);
			// If filter doesn't provide URL, try to construct from plugin slug (fallback).
			if ( $plugin_url === null ) {
				$plugin_url = plugins_url( '', dirname( dirname( __DIR__ ) ) . '/../../' . $plugin_slug );
			}
		}

		// Generate text domain from plugin slug if not provided.
		if ( $text_domain === null ) {
			$text_domain = apply_filters(
				'flux_plugins_common_text_domain',
				str_replace( '-', '_', $plugin_slug ),
				$plugin_slug
			);
		}

		// TODO: Initialize Logger with plugin slug when Logger service is available.
		// For now, compatibility validation will work without logging.
		if ( $logger === null ) {
			$logger = null; // Placeholder until Logger is migrated to shared library.
		}

		// Create shared external API client for compatibility checks.
		// Shared API client will use constants internally (FLUX_PLUGINS_COMMON_*).
		$shared_api_client = new ExternalApiClient( $logger );

		// Generate cache option name based on plugin slug.
		$cache_option_name = 'flux_plugins_compatibility_cache_' . sanitize_key( $plugin_slug );

		// Initialize compatibility validator with dependencies.
		$compatibility_validator = new CompatibilityValidator(
			$logger,
			$shared_api_client,
			$plugin_slug,
			$plugin_version,
			$cache_option_name
		);

		// Store validator for this plugin.
		self::$validators[ $plugin_slug ] = $compatibility_validator;

		// Invalidate cache on version change.
		$compatibility_validator->invalidate_on_version_change();

		// Initialize notice handler (only in admin context).
		if ( is_admin() ) {
			// Generate namespaced AJAX action and transient prefix based on plugin slug.
			$ajax_action = 'flux_plugins_compatibility_dismiss_' . sanitize_key( $plugin_slug );
			$dismissal_transient_prefix = 'flux_plugins_compatibility_dismissed_' . sanitize_key( $plugin_slug ) . '_';

			$notice_handler = new CompatibilityNoticeHandler(
				$compatibility_validator,
				$text_domain,
				$plugin_version,
				$plugin_url,
				$dismissal_transient_prefix,
				$ajax_action
			);
			$notice_handler->init();

			// Store notice handler for this plugin.
			self::$notice_handlers[ $plugin_slug ] = $notice_handler;

			// Enqueue compatibility assets (only once, shared across all plugins).
			$this->enqueue_assets( $plugin_url, $plugin_version );
		}
	}

	/**
	 * Enqueue compatibility assets.
	 *
	 * Ensures compatibility JavaScript is only enqueued once, even when multiple plugins
	 * use the shared library. Uses WordPress action hooks to track enqueuing state.
	 *
	 * @since 1.0.0
	 * @param string $plugin_url     Plugin URL for asset paths.
	 * @param string $plugin_version Plugin version for cache busting.
	 * @return void
	 */
	private function enqueue_assets( $plugin_url, $plugin_version ) {
		// Ensure assets are only enqueued once per request (shared across all plugins).
		if ( did_action( 'flux_suite/compatibility_service/assets_enqueued' ) ) {
			return;
		}

		// Hook into admin_enqueue_scripts to enqueue the compatibility dismiss script.
		add_action( 'admin_enqueue_scripts', function() use ( $plugin_url, $plugin_version ) {
			// Try to get shared library URL from filter (plugins can provide it).
			// This is necessary because the shared library is in vendor-prefixed, which varies per plugin.
			$shared_lib_url = apply_filters( 'flux_plugins_common_shared_lib_url', null );

			// If shared lib URL not provided, try to construct from current file location.
			if ( $shared_lib_url === null ) {
				// Get the URL of the shared library directory.
				$shared_lib_dir = dirname( dirname( __DIR__ ) );
				$shared_lib_url = plugins_url( '', $shared_lib_dir . '/assets' );
			}

			// Fallback to plugin URL if shared lib URL still not available.
			if ( empty( $shared_lib_url ) ) {
				$shared_lib_url = $plugin_url;
			}

			// Determine script path based on SCRIPT_DEBUG (development vs production).
			$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
			$script_path  = $script_debug
				? 'assets/js/src/admin/compatibility-dismiss.js' // Source file for development.
				: 'assets/js/dist/compatibility-dismiss.bundle.js'; // Built file for production.

			// Enqueue script with jQuery as dependency (WordPress Plugin Guidelines #13).
			// Use a unique handle to avoid conflicts.
			wp_enqueue_script(
				'flux-plugins-common-compatibility-dismiss',
				trailingslashit( $shared_lib_url ) . $script_path,
				[ 'jquery' ], // jQuery dependency - WordPress default library.
				$plugin_version,
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
		do_action( 'flux_suite/compatibility_service/assets_enqueued' );
	}

	/**
	 * Get compatibility validator instance for a plugin.
	 *
	 * Factory method following the singleton pattern. Use this to access
	 * the compatibility validator for a specific plugin.
	 *
	 * @since 1.0.0
	 * @param string|null $plugin_slug Optional plugin slug. If not provided, uses the current plugin
	 *                                  (last plugin that called init_plugin()).
	 * @return CompatibilityValidator|null Compatibility validator instance or null if not initialized.
	 */
	public static function get_validator( $plugin_slug = null ) {
		// If no plugin slug provided, use the current plugin.
		if ( $plugin_slug === null ) {
			$plugin_slug = self::$current_plugin_slug;
		}

		if ( $plugin_slug === null ) {
			return null;
		}

		return isset( self::$validators[ $plugin_slug ] ) ? self::$validators[ $plugin_slug ] : null;
	}

	/**
	 * Get notice handler instance for a plugin.
	 *
	 * Factory method following the singleton pattern. Use this to access
	 * the notice handler for a specific plugin.
	 *
	 * @since 1.0.0
	 * @param string|null $plugin_slug Optional plugin slug. If not provided, uses the current plugin
	 *                                  (last plugin that called init_plugin()).
	 * @return CompatibilityNoticeHandler|null Notice handler instance or null if not initialized.
	 */
	public static function get_notice_handler( $plugin_slug = null ) {
		// If no plugin slug provided, use the current plugin.
		if ( $plugin_slug === null ) {
			$plugin_slug = self::$current_plugin_slug;
		}

		if ( $plugin_slug === null ) {
			return null;
		}

		return isset( self::$notice_handlers[ $plugin_slug ] ) ? self::$notice_handlers[ $plugin_slug ] : null;
	}
}

