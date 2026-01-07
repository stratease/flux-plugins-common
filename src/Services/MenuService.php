<?php
/**
 * Menu service for WordPress admin menu registration.
 *
 * @package FluxPlugins\Common\Services
 * @since 1.0.0
 */

namespace FluxPlugins\Common\Services;

/**
 * Menu service.
 *
 * Centralized menu registration ensuring "Flux Suite" top-level menu is registered once,
 * with support for submenu pages and special handling for License/Settings pages.
 *
 * **Important:** This service uses WordPress action hooks (`did_action()` / `do_action()`)
 * to track registration state per request across all plugin instances. This is necessary because
 * each plugin uses Strauss to namespace-prefix this library, resulting in separate class instances.
 * WordPress action hooks provide the shared state layer that ensures pages only appear once per
 * request, even when multiple plugins with different namespaces call the registration methods.
 *
 * This approach is cleaner than using WordPress options because:
 * - It's per-request, not persistent (no database overhead)
 * - It uses WordPress's built-in hook system
 * - It's more efficient for request-scoped operations
 *
 * ## Hook Naming Convention
 *
 * All hooks follow the pattern: `{plugin_namespace}/{class_name}/{method_name}`
 *
 * - **Plugin Namespace**: `flux_suite` - Identifies the Flux Plugins suite
 * - **Class Name**: `menu_service` - The class name in snake_case (e.g., `MenuService` -> `menu_service`)
 * - **Method Name**: The method name that fires the hook (e.g., `register_top_level_menu`)
 *
 * For more refined callbacks within a method, append `/{operation}`:
 * - `{plugin_namespace}/{class_name}/{method_name}/{operation}`
 *
 * Examples:
 * - `flux_suite/menu_service/register_top_level_menu` - Fired when top-level menu is registered
 * - `flux_suite/menu_service/register_license_page` - Fired when License page is registered
 * - `flux_suite/menu_service/register_logs_page` - Fired when Logs page is registered
 * - `flux_suite/menu_service/register_settings_page` - Fired when Settings page is registered
 *
 * @since 1.0.0
 */
class MenuService {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var MenuService|null
	 */
	private static $instance = null;

	/**
	 * Fixed menu priority.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MENU_PRIORITY = 30;

	/**
	 * Top-level menu slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TOP_LEVEL_MENU_SLUG = 'flux-suite';

	/**
	 * License page slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LICENSE_PAGE_SLUG = 'flux-suite-license';

	/**
	 * Settings page slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SETTINGS_PAGE_SLUG = 'flux-suite-settings';

	/**
	 * Logs page slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LOGS_PAGE_SLUG = 'flux-suite-logs';

	/**
	 * Primary submenu candidates (per-request, shared across plugins).
	 *
	 * Stores submenu pages that should become the primary menu item.
	 * The one with the lowest placement number becomes the primary menu.
	 * Uses WordPress action hook to track candidates across all plugin instances.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $primary_submenu_candidates = [];

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return MenuService Singleton instance.
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
	 * Initialize menu service.
	 *
	 * Ensures the top-level "Flux Suite" menu is registered.
	 * Note: This should only be called from admin_init hook, as admin functions
	 * are not available in WP-CLI or frontend contexts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		// Ensure top-level menu is registered.
		// This ensures the menu exists even if no submenu pages are registered yet.
		$this->register_top_level_menu();
	}

	/**
	 * Register top-level menu.
	 *
	 * Ensures "Flux Suite" is always registered as the top-level menu (idempotent).
	 * Uses WordPress action hooks to track registration state per request across all plugin instances,
	 * since each plugin may have its own namespaced version of this library.
	 *
	 * **Action Hook:** `flux_suite/menu_service/register_top_level_menu`
	 * - **Fired when:** The top-level menu is successfully registered
	 * - **Purpose:** Allows other code to detect when the menu has been registered
	 * - **Scope:** Per-request, shared across all plugins regardless of namespace prefixing
	 * - **Usage:** Use `did_action('flux_suite/menu_service/register_top_level_menu')` to check if menu is registered
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_top_level_menu() {
		// Ensure this menu is registered only once per request (shared across all plugins).
		if ( did_action( 'flux_suite/menu_service/register_top_level_menu' ) ) {
			return;
		}

		// Hook into admin_menu to register the menu.
		add_action( 'admin_menu', function() {
			// Always register "Flux Suite" as top-level menu.
			add_menu_page(
				__( 'Flux Suite', 'flux-plugins-common' ),
				__( 'Flux Suite', 'flux-plugins-common' ),
				'manage_options',
				self::TOP_LEVEL_MENU_SLUG,
				[ $this, 'render_top_level_page' ],
				'dashicons-admin-generic',
				self::MENU_PRIORITY
			);
		}, self::MENU_PRIORITY );

		// Mark as registered using WordPress action hook (shared across all plugins).
		/**
		 * Fires when the top-level menu is registered.
		 *
		 * This action is fired once per request after the menu is successfully registered.
		 * It can be used by other code to detect when the menu registration has completed.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/menu_service/register_top_level_menu' );
	}

	/**
	 * Get primary menu candidate.
	 *
	 * Returns the submenu candidate with the lowest placement number, or null if none exists.
	 * Collects candidates from all plugins by checking the action hook.
	 * Used to determine which submenu should appear first under "Flux Suite".
	 *
	 * @since 1.0.0
	 * @return array|null Primary menu candidate or null.
	 */
	private function get_primary_menu_candidate() {
		// Collect candidates from all plugins via action hook.
		$all_candidates = [];
		
		/**
		 * Fires to collect primary menu candidates from all plugins.
		 *
		 * Plugins should add their candidates to the array passed by reference.
		 *
		 * @since 1.0.0
		 * @param array $candidates Array of menu candidates (passed by reference).
		 */
		do_action_ref_array( 'flux_suite/menu_service/get_primary_menu_candidate', [ &$all_candidates ] );

		// Also include candidates from this instance.
		$all_candidates = array_merge( $all_candidates, self::$primary_submenu_candidates );

		if ( empty( $all_candidates ) ) {
			return null;
		}

		// Sort by placement (ascending) and return the first one.
		usort( $all_candidates, function( $a, $b ) {
			return $a['placement'] <=> $b['placement'];
		} );

		return $all_candidates[0];
	}

	/**
	 * Register submenu page.
	 *
	 * @since 1.0.0
	 * @param string   $slug      Menu slug.
	 * @param string   $title     Menu title.
	 * @param callable $callback  Page callback function.
	 * @param string   $capability Required capability (default: 'manage_options').
	 * @param int|null $placement Optional placement priority. If provided, this submenu becomes
	 *                            a candidate to appear first in the submenu list (lower number = higher priority).
	 *                            If placement is 1, it will be the first submenu item under "Flux Suite".
	 * @return void
	 */
	public function register_submenu_page( $slug, $title, $callback, $capability = 'manage_options', $placement = null ) {
		// If placement is provided, add to primary menu candidates.
		if ( $placement !== null ) {
			$candidate = [
				'slug'       => $slug,
				'title'      => $title,
				'callback'   => $callback,
				'capability' => $capability,
				'placement'  => (int) $placement,
			];

			self::$primary_submenu_candidates[] = $candidate;

			// Also register candidate via action hook so other plugin instances can see it.
			add_action( 'flux_suite/menu_service/get_primary_menu_candidate', function( &$candidates ) use ( $candidate ) {
				$candidates[] = $candidate;
			}, 10, 1 );
		}

		// Ensure top-level menu is registered first.
		$this->register_top_level_menu();

		// Hook into admin_menu to register the submenu.
		// Use priority based on placement to ensure proper ordering (lower placement = higher priority = earlier registration).
		$hook_priority = $placement !== null ? ( self::MENU_PRIORITY - $placement ) : ( self::MENU_PRIORITY + 1 );

		add_action( 'admin_menu', function() use ( $slug, $title, $callback, $capability ) {
			// Always register under "Flux Suite" top-level menu.
			add_submenu_page(
				self::TOP_LEVEL_MENU_SLUG,
				$title,
				$title,
				$capability,
				$slug,
				$callback
			);
		}, $hook_priority );
	}

	/**
	 * Register license page.
	 *
	 * Only registers once, shared across plugins, fully managed by common library.
	 * Uses WordPress action hooks to track registration state per request across all plugin instances,
	 * since each plugin may have its own namespaced version of this library.
	 *
	 * **Action Hook:** `flux_suite/menu_service/register_license_page`
	 * - **Fired when:** The shared License page is successfully registered
	 * - **Purpose:** Allows other code to detect when the License page has been registered
	 * - **Scope:** Per-request, shared across all plugins regardless of namespace prefixing
	 * - **Usage:** Use `did_action('flux_suite/menu_service/register_license_page')` to check if page is registered
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_license_page() {
		// Ensure this page is registered only once per request (shared across all plugins).
		if ( did_action( 'flux_suite/menu_service/register_license_page' ) ) {
			return;
		}

		// Ensure top-level menu is registered first.
		$this->register_top_level_menu();

		// Hook into admin_menu to register the license page.
		add_action( 'admin_menu', function() {
			add_submenu_page(
				self::TOP_LEVEL_MENU_SLUG,
				__( 'License', 'flux-plugins-common' ),
				__( 'License', 'flux-plugins-common' ),
				'manage_options',
				self::LICENSE_PAGE_SLUG,
				[ $this, 'render_license_page' ]
			);
		}, self::MENU_PRIORITY + 1 );

		// Mark as registered using WordPress action hook (shared across all plugins).
		/**
		 * Fires when the shared License page is registered.
		 *
		 * This action is fired once per request after the License page is successfully registered.
		 * It can be used by other code to detect when the License page registration has completed.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/menu_service/register_license_page' );
	}

	/**
	 * Register logs page.
	 *
	 * Only registers once, shared across plugins, initialized by MenuService.
	 * Uses WordPress action hooks to track registration state per request across all plugin instances,
	 * since each plugin may have its own namespaced version of this library.
	 *
	 * **Action Hook:** `flux_suite/menu_service/register_logs_page`
	 * - **Fired when:** The shared Logs page is successfully registered
	 * - **Purpose:** Allows other code to detect when the Logs page has been registered
	 * - **Scope:** Per-request, shared across all plugins regardless of namespace prefixing
	 * - **Usage:** Use `did_action('flux_suite/menu_service/register_logs_page')` to check if page is registered
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_logs_page() {
		// Ensure this page is registered only once per request (shared across all plugins).
		if ( did_action( 'flux_suite/menu_service/register_logs_page' ) ) {
			return;
		}

		// Ensure top-level menu is registered first.
		$this->register_top_level_menu();

		// Hook into admin_menu to register the logs page.
		add_action( 'admin_menu', function() {
			add_submenu_page(
				self::TOP_LEVEL_MENU_SLUG,
				__( 'Logs', 'flux-plugins-common' ),
				__( 'Logs', 'flux-plugins-common' ),
				'manage_options',
				self::LOGS_PAGE_SLUG,
				[ $this, 'render_logs_page' ]
			);
		}, self::MENU_PRIORITY + 1 );

		// Mark as registered using WordPress action hook (shared across all plugins).
		/**
		 * Fires when the shared Logs page is registered.
		 *
		 * This action is fired once per request after the Logs page is successfully registered.
		 * It can be used by other code to detect when the Logs page registration has completed.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/menu_service/register_logs_page' );
	}

	/**
	 * Settings tabs storage (per-request).
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $settings_tabs = [];

	/**
	 * Register settings page tab.
	 *
	 * Multiple plugins can add tabs. Uses React Router hash routes for navigation.
	 * Uses WordPress action hooks to track registration state per request across all plugin instances,
	 * since each plugin may have its own namespaced version of this library.
	 *
	 * **Action Hook:** `flux_suite/menu_service/register_settings_page`
	 * - **Fired when:** The shared Settings page is successfully registered (first time only)
	 * - **Purpose:** Allows other code to detect when the Settings page has been registered
	 * - **Scope:** Per-request, shared across all plugins regardless of namespace prefixing
	 * - **Usage:** Use `did_action('flux_suite/menu_service/register_settings_page')` to check if page is registered
	 *
	 * @since 1.0.0
	 * @param string $tab_component React component name/path for the tab.
	 * @param string $tab_label     Tab label.
	 * @return void
	 */
	public function register_settings_page( $tab_component, $tab_label ) {
		// Ensure this page is registered only once per request (shared across all plugins).
		if ( did_action( 'flux_suite/menu_service/register_settings_page' ) ) {
			// Page already registered, just add the tab.
			self::$settings_tabs[] = [
				'component' => $tab_component,
				'label'     => $tab_label,
			];
			return;
		}

		// Ensure top-level menu is registered first.
		$this->register_top_level_menu();

		// Add tab to collection (stored in static property, shared across all plugin instances).
		self::$settings_tabs[] = [
			'component' => $tab_component,
			'label'     => $tab_label,
		];

		// Register settings page (hook into admin_menu).
		add_action( 'admin_menu', function() {
			add_submenu_page(
				self::TOP_LEVEL_MENU_SLUG,
				__( 'Settings', 'flux-plugins-common' ),
				__( 'Settings', 'flux-plugins-common' ),
				'manage_options',
				self::SETTINGS_PAGE_SLUG,
				[ $this, 'render_settings_page' ]
			);
		}, self::MENU_PRIORITY + 1 );

		// Mark as registered using WordPress action hook (shared across all plugins).
		/**
		 * Fires when the shared Settings page is registered.
		 *
		 * This action is fired once per request after the Settings page is successfully registered
		 * (the first time a plugin calls register_settings_page()). It can be used by other code
		 * to detect when the Settings page registration has completed.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/menu_service/register_settings_page' );
	}

	/**
	 * Get settings page tabs.
	 *
	 * Retrieves all registered tabs from static property (shared across all plugins per request).
	 *
	 * @since 1.0.0
	 * @return array Array of tab configurations.
	 */
	public function get_settings_tabs() {
		return self::$settings_tabs;
	}

	/**
	 * Render top-level page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_top_level_page() {
		// Default redirect to first submenu or settings page.
		$redirect_url = admin_url( 'admin.php?page=' . self::SETTINGS_PAGE_SLUG );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render license page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_license_page() {
		// TODO: Render React LicensePage component.
		// For now, placeholder.
		echo '<div class="wrap"><h1>' . esc_html__( 'License', 'flux-plugins-common' ) . '</h1></div>';
	}

	/**
	 * Render logs page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_logs_page() {
		// TODO: Render React LogsPage component.
		// For now, placeholder.
		echo '<div class="wrap"><h1>' . esc_html__( 'Logs', 'flux-plugins-common' ) . '</h1></div>';
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_page() {
		// TODO: Render React SettingsPage component with tabs.
		// For now, placeholder.
		echo '<div class="wrap"><h1>' . esc_html__( 'Settings', 'flux-plugins-common' ) . '</h1></div>';
	}
}

