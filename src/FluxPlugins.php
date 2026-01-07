<?php
/**
 * Main initialization service for Flux Plugins suite.
 *
 * @package FluxPlugins\Common
 * @since 1.0.0
 */

namespace FluxPlugins\Common;

use FluxPlugins\Common\Account\AccountIdService;
use FluxPlugins\Common\Services\MenuService;
use FluxPlugins\Common\Services\CompatibilityService;

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
	 * Plugin slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_version;


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
	 * Hooks into WordPress 'init' and 'admin_init' actions to ensure core systems are initialized first.
	 * This method registers the initialization callbacks and should be called early in the plugin lifecycle.
	 *
	 * @since 1.0.0
	 * @param string $plugin_slug    Plugin slug (e.g., 'flux-media-optimizer').
	 * @param string $plugin_version Plugin version (e.g., '1.0.0').
	 * @return void
	 */
	public static function init( $plugin_slug, $plugin_version ) {
		// Load constants first.
		require_once dirname( __DIR__ ) . '/includes/constants.php';

		$instance = self::get_instance();
		$instance->plugin_slug    = $plugin_slug;
		$instance->plugin_version = $plugin_version;

		// Hook into WordPress 'init' action for general initialization (account ID, etc.).
		add_action( 'init', [ $instance, 'do_init' ], 10 );

		// Hook into WordPress 'admin_init' action for admin-specific initialization (menus, etc.).
		add_action( 'admin_init', [ $instance, 'do_admin_init' ], 10 );
	}

	/**
	 * Perform general initialization after WordPress core is ready.
	 *
	 * This method is called on the 'init' hook and performs general initialization:
	 * - Ensures account ID exists (via AccountIdService)
	 * - Initializes compatibility validation system
	 *
	 * This method is idempotent and can be called multiple times safely.
	 * Note: Since each plugin may have its own namespaced version of this library,
	 * the singleton pattern doesn't work across plugins. However, the underlying
	 * services use WordPress action hooks to track shared state.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function do_init() {
		// Ensure account ID exists (available in all contexts: admin, frontend, WP-CLI).
		$account_service = AccountIdService::get_instance();
		$account_service->ensure_account_id();

		// Initialize compatibility validation system via CompatibilityService.
		$compatibility_service = CompatibilityService::get_instance();
		$compatibility_service->init_plugin( $this->plugin_slug, $this->plugin_version );

		// TODO: Initialize Logger with plugin slug when Logger service is available.
		// Logger::get_instance()->init( $this->plugin_slug );
	}

	/**
	 * Perform admin-specific initialization after WordPress admin is ready.
	 *
	 * This method is called on the 'admin_init' hook and performs admin-specific initialization:
	 * - Registers top-level "Flux Suite" menu (via MenuService)
	 * - Registers License page (via MenuService)
	 * - Registers Logs page (via MenuService)
	 *
	 * This method only runs in admin context, not in WP-CLI or frontend.
	 * This method is idempotent and can be called multiple times safely.
	 * Note: Since each plugin may have its own namespaced version of this library,
	 * the singleton pattern doesn't work across plugins. However, the underlying
	 * services (MenuService) use WordPress action hooks to track shared state,
	 * ensuring pages only appear once even when multiple plugins call this method.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function do_admin_init() {
		// Initialize menu service.
		$menu_service = MenuService::get_instance();
		$menu_service->init();
	}

}

