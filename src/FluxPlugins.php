<?php
/**
 * Main initialization service for Flux Plugins suite.
 *
 * @package FluxPlugins\Common
 * @since 1.0.0
 */

namespace FluxPlugins\Common;

use FluxPlugins\Common\Account\AccountIdService;
use FluxPlugins\Common\WordPress\MenuService;

/**
 * Main Flux Plugins initialization service.
 *
 * Single entry point for all Flux Plugins to initialize the common library.
 * Handles account ID registration, menu setup, and required pages.
 *
 * @since 1.0.0
 */
class FluxPlugins {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var FluxPlugins|null
	 */
	private static $instance = null;

	/**
	 * Whether initialization has been called.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return FluxPlugins Singleton instance.
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
	 * Initialize Flux Plugins common library.
	 *
	 * Main initialization method that:
	 * - Ensures account ID exists (via AccountIdService)
	 * - Registers top-level "Flux Suite" menu (via MenuService)
	 * - Registers License page (via MenuService)
	 * - Registers Logs page (via MenuService)
	 * - Initializes Logger with plugin slug (when Logger service is available)
	 *
	 * This method is idempotent and can be called multiple times safely.
	 * Note: Since each plugin may have its own namespaced version of this library,
	 * the singleton pattern doesn't work across plugins. However, the underlying
	 * services (MenuService, AccountIdService) use WordPress options to track
	 * shared state, ensuring pages only appear once even when multiple plugins
	 * call this method.
	 *
	 * @since 1.0.0
	 * @param string $plugin_slug    Plugin slug (e.g., 'flux-media-optimizer').
	 * @param string $plugin_version Plugin version (e.g., '1.0.0').
	 * @return void
	 */
	public static function init( $plugin_slug, $plugin_version ) {
		// Note: We don't check self::$initialized here because each plugin has its own
		// namespaced version of this class. Instead, the underlying services use
		// WordPress options to ensure idempotency across all plugins.

		$instance = self::get_instance();

		// Ensure account ID exists.
		$account_service = AccountIdService::get_instance();
		$account_service->ensure_account_id();

		// Initialize menu service.
		$menu_service = MenuService::get_instance();
		$menu_service->init();

		// Register required pages.
		// These methods use WordPress options internally to ensure they only register once,
		// even when called from multiple plugins with different namespaces.
		$menu_service->register_top_level_menu();
		$menu_service->register_license_page();
		$menu_service->register_logs_page();

		// TODO: Initialize Logger with plugin slug when Logger service is available.
		// Logger::get_instance()->init( $plugin_slug );
	}
}

