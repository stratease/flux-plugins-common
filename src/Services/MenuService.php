<?php
/**
 * Menu service for WordPress admin menu registration.
 *
 * @package FluxPlugins\Common\Services
 * @since 1.0.0
 */

namespace FluxPlugins\Common\Services;

use FluxPlugins\Common\Services\I18n;

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
	 * Ensures the top-level "Flux Suite" menu is registered and initializes the plugin registry.
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

		add_action('init', [$this, 'do_init']);
	}

	public function do_init() {
		// Initialize the Flux Suite plugin registry (marketing purposes only).
		$this->init_plugin_registry();

		// Note: License page and Logs page registration is optional and should be called
		// by individual plugins if they need these pages. The common library does not
		// automatically register them.
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
		// Ensure this menu hook is registered only once per request (shared across all plugins).
		// Use WordPress action hook to track registration state across Strauss-namespaced instances.
		if ( did_action( 'flux_suite/menu_service/register_top_level_menu' ) ) {
			return;
		}

		// Mark as hooked to prevent duplicate hooks (shared across all plugins).
		/**
		 * Fires when the top-level menu hook is registered.
		 *
		 * This action is fired once per request when the admin_menu hook is registered.
		 * It can be used to detect when the hook registration has occurred.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/menu_service/register_top_level_menu' );

		// Hook into admin_menu to register the menu.
		// Use method reference instead of closure to ensure $this is available.
		add_action( 'admin_menu', [ $this, 'do_register_top_level_menu' ], 1 );
	}

	/**
	 * Register top-level menu callback.
	 *
	 * Called by WordPress admin_menu hook. Registers the "Flux Suite" top-level menu.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function do_register_top_level_menu() {
		// Always register "Flux Suite" as top-level menu.
		add_menu_page(
			__( 'Flux Suite', I18n::domain() ),
			__( 'Flux Suite', I18n::domain() ),
			'manage_options',
			self::TOP_LEVEL_MENU_SLUG,
			[ $this, 'render_top_level_page' ],
			'dashicons-admin-generic',
			self::MENU_PRIORITY
		);
	}

	/**
	 * Register submenu page.
	 *
	 * @since 1.0.0
	 * @param string   $slug      Menu slug.
	 * @param string   $title     Menu title.
	 * @param callable $callback  Page callback function.
	 * @param string   $capability Required capability (default: 'manage_options').
	 * @param int|null $placement Optional placement priority. If provided, controls the registration order
	 *                            (lower number = higher priority = earlier registration). This affects the
	 *                            order in which submenu items appear under "Flux Suite".
	 * @return void
	 */
	public function register_submenu_page( $slug, $title, $callback, $capability = 'manage_options', $placement = null ) {
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

		// Mark as hooked to prevent duplicate hooks (shared across all plugins).
		/**
		 * Fires when the License page hook is registered.
		 *
		 * This action is fired once per request when the admin_menu hook is registered.
		 * It can be used to detect when the hook registration has occurred.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/menu_service/register_license_page' );

		// Hook into admin_menu to register the license page.
		// Use method reference instead of closure to ensure $this is available.
		add_action( 'admin_menu', [ $this, 'do_register_license_page' ], self::MENU_PRIORITY + 1 );

		// Hook into admin_enqueue_scripts to enqueue license page scripts.
		// This must be separate from page registration to ensure scripts are enqueued at the right time.
		$this->enqueue_license_page_scripts();
	}

	/**
	 * Register license page callback.
	 *
	 * Called by WordPress admin_menu hook. Registers the "License" submenu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function do_register_license_page() {
		add_submenu_page(
			self::TOP_LEVEL_MENU_SLUG,
			__( 'License', I18n::domain() ),
			__( 'License', I18n::domain() ),
			'manage_options',
			self::LICENSE_PAGE_SLUG,
			[ $this, 'render_license_page' ]
		);
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

		// Mark as hooked to prevent duplicate hooks (shared across all plugins).
		/**
		 * Fires when the Logs page hook is registered.
		 *
		 * This action is fired once per request when the admin_menu hook is registered.
		 * It can be used to detect when the hook registration has occurred.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/menu_service/register_logs_page' );

		// Hook into admin_menu to register the logs page.
		// Use method reference instead of closure to ensure $this is available.
		add_action( 'admin_menu', [ $this, 'do_register_logs_page' ], self::MENU_PRIORITY + 1 );

		// Hook into admin_enqueue_scripts to enqueue logs page scripts.
		// This must be separate from page registration to ensure scripts are enqueued at the right time.
		$this->enqueue_logs_page_scripts();
	}

	/**
	 * Register logs page callback.
	 *
	 * Called by WordPress admin_menu hook. Registers the "Logs" submenu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function do_register_logs_page() {
		add_submenu_page(
			self::TOP_LEVEL_MENU_SLUG,
			__( 'Logs', I18n::domain() ),
			__( 'Logs', I18n::domain() ),
			'manage_options',
			self::LOGS_PAGE_SLUG,
			[ $this, 'render_logs_page' ]
		);
	}

	/**
	 * Settings tabs storage (per-request).
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $settings_tabs = [];

	/**
	 * Registered Flux Suite plugins (per-request, shared across plugins).
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $registered_plugins = [];

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

		// Add tab to collection (stored in static property, shared across all plugin instances).
		self::$settings_tabs[] = [
			'component' => $tab_component,
			'label'     => $tab_label,
		];

		// Mark as hooked to prevent duplicate hooks (shared across all plugins).
		/**
		 * Fires when the Settings page hook is registered.
		 *
		 * This action is fired once per request when the admin_menu hook is registered.
		 * It can be used to detect when the hook registration has occurred.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/menu_service/register_settings_page' );

		// Register settings page (hook into admin_menu).
		// Use method reference instead of closure to ensure $this is available.
		add_action( 'admin_menu', [ $this, 'do_register_settings_page' ], self::MENU_PRIORITY + 1 );
	}

	/**
	 * Register settings page callback.
	 *
	 * Called by WordPress admin_menu hook. Registers the "Settings" submenu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function do_register_settings_page() {
		add_submenu_page(
			self::TOP_LEVEL_MENU_SLUG,
			__( 'Settings', I18n::domain() ),
			__( 'Settings', I18n::domain() ),
			'manage_options',
			self::SETTINGS_PAGE_SLUG,
			[ $this, 'render_settings_page' ]
		);
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
	 * Initialize Flux Suite plugin registry.
	 *
	 * Central location for marketing all Flux Suite plugins.
	 * Plugins are hard-coded here for marketing purposes only.
	 * When a plugin becomes active, it will automatically be detected and sorted to the bottom.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_plugin_registry() {
		// Ensure this registry is initialized only once per request (shared across all plugins).
		if ( did_action( 'flux_suite/menu_service/init_plugin_registry' ) ) {
			return;
		}

		/**
		 * Fires when the plugin registry is initialized.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/menu_service/init_plugin_registry' );

		// Register all Flux Suite plugins (marketing purposes only).
		// Active plugins will be automatically detected and sorted to the bottom.

		// Flux Media Optimizer
		$this->add_plugin_to_registry(
			'flux-media-optimizer',
			__( 'Media Optimizer', I18n::domain() ),
			__( 'One-click image (AVIF & WebP) and video optimization for WordPress.', I18n::domain() ),
			'flux-media-optimizer/flux-media-optimizer.php',
			'admin.php?page=flux-media-optimizer',
			'https://fluxplugins.com/media-optimizer',
			'dashicons-admin-media'
		);

		// Flux AI Featured Image Generator
		$this->add_plugin_to_registry(
			'flux-ai-featured-image-generator',
			__( 'AI Featured Image Generator', I18n::domain() ),
			__( 'Generate featured images for your posts and pages with AI.', I18n::domain() ),
			null,//'flux-ai-featured-image-generator/flux-ai-featured-image-generator.php',
			'admin.php?page=flux-ai-featured-image-generator',
			'https://fluxplugins.com/ai-featured-image-generator',
			'dashicons-admin-media'
		);

		// Flux Unused Media Cleaner
		$this->add_plugin_to_registry(
			'flux-unused-media-cleaner',
			__( 'Unused Media Cleaner', I18n::domain() ),
			__( 'Clean up unused media files from your WordPress site.', I18n::domain() ),
			null,//'flux-unused-media-cleaner/flux-unused-media-cleaner.php',
			'admin.php?page=flux-unused-media-cleaner',
			'https://fluxplugins.com/unused-media-cleaner',
			'dashicons-admin-generic'
		);

		// Flux AI Alt Text Generator
		$this->add_plugin_to_registry(
			'flux-ai-alt-text-generator',
			__( 'AI Alt Text Generator', I18n::domain() ),
			__( 'Generate alt text for your images with AI.', I18n::domain() ),
			null,//'flux-ai-alt-text-generator/flux-ai-alt-text-generator.php',
			'admin.php?page=flux-ai-alt-text-generator',
			'https://fluxplugins.com/ai-alt-text-generator',
			'dashicons-admin-media'
		);


		// Add more plugins here as they are planned or developed.
		// Example for a planned plugin:
		// $this->add_plugin_to_registry(
		//     'flux-new-plugin',
		//     __( 'New Plugin', I18n::domain() ),
		//     __( 'Description of the new plugin.', I18n::domain() ),
		//     null, // No plugin file = Planned
		//     null,
		//     'https://fluxplugins.com/new-plugin',
		//     'dashicons-admin-generic'
		// );
	}

	/**
	 * Add plugin to registry (internal method).
	 *
	 * @since 1.0.0
	 * @param string      $slug         Plugin slug.
	 * @param string      $name         Plugin display name.
	 * @param string      $description  Plugin description.
	 * @param string|null $plugin_file  Plugin main file path relative to wp-content/plugins/.
	 *                                  If null, plugin is treated as "Planned".
	 * @param string|null $admin_url    Admin page URL for active plugins.
	 * @param string|null $marketing_url Marketing page URL.
	 * @param string|null $icon         Dashicon class name.
	 * @return void
	 */
	private function add_plugin_to_registry( $slug, $name, $description, $plugin_file = null, $admin_url = null, $marketing_url = null, $icon = null ) {
		$plugin_data = [
			'slug'          => $slug,
			'name'          => $name,
			'description'   => $description,
			'plugin_file'   => $plugin_file,
			'admin_url'     => $admin_url,
			'marketing_url' => $marketing_url,
			'icon'          => $icon ? $icon : 'dashicons-admin-plugins',
		];

		// Store in static property (shared across all plugin instances per request).
		self::$registered_plugins[] = $plugin_data;
	}

	/**
	 * Get all registered Flux Suite plugins.
	 *
	 * Retrieves plugins from static property, then checks activation status.
	 * Active plugins are sorted to the bottom of the list.
	 *
	 * @since 1.0.0
	 * @return array Array of plugin configurations with activation status.
	 */
	public function get_registered_plugins() {
		$plugins = self::$registered_plugins;

		// Ensure WordPress plugin functions are available.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Check activation status and add status to each plugin.
		$active_plugins = [];
		$inactive_plugins = [];
		$planned_plugins = [];

		foreach ( $plugins as $plugin ) {
			$plugin['is_active'] = false;
			$plugin['is_planned'] = false;

			if ( ! empty( $plugin['plugin_file'] ) ) {
				// Check if plugin is active.
				$plugin['is_active'] = is_plugin_active( $plugin['plugin_file'] );
			} else {
				// No plugin file means it's "Planned".
				$plugin['is_planned'] = true;
			}

			// Categorize plugins.
			if ( $plugin['is_planned'] ) {
				$planned_plugins[] = $plugin;
			} elseif ( $plugin['is_active'] ) {
				$active_plugins[] = $plugin;
			} else {
				$inactive_plugins[] = $plugin;
			}
		}

		// Combine: inactive first, then planned, then active (at bottom).
		return array_merge( $inactive_plugins, $planned_plugins, $active_plugins );
	}

	/**
	 * Render top-level page.
	 *
	 * Displays an overview of all Flux Suite plugins in an attractive grid layout.
	 * Active plugins are shown at the bottom, inactive and planned plugins at the top.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_top_level_page() {
		$plugins = $this->get_registered_plugins();

		// If no plugins registered, redirect to settings.
		if ( empty( $plugins ) ) {
			$redirect_url = admin_url( 'admin.php?page=' . self::SETTINGS_PAGE_SLUG );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Flux Suite', I18n::domain() ); ?></h1>
			<p class="description">
				<?php echo esc_html__( 'Manage your Flux Plugins suite. Active plugins are shown at the bottom.', I18n::domain() ); ?>
			</p>

			<div class="flux-suite-plugins-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
				<?php foreach ( $plugins as $plugin ) : ?>
					<?php
					$is_active = ! empty( $plugin['is_active'] );
					$is_planned = ! empty( $plugin['is_planned'] );
					$card_class = 'flux-suite-plugin-card';
					$card_style = 'border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.04); transition: box-shadow 0.15s ease-in-out;';
					
					if ( $is_active ) {
						$card_class .= ' flux-suite-plugin-active';
						$card_style .= ' border-left: 4px solid #00a32a;';
					} elseif ( $is_planned ) {
						$card_class .= ' flux-suite-plugin-planned';
						$card_style .= ' border-left: 4px solid #dba617; opacity: 0.9;';
					} else {
						$card_class .= ' flux-suite-plugin-inactive';
						$card_style .= ' border-left: 4px solid #c3c4c7; opacity: 0.8;';
					}

					// Determine link URL.
					$link_url = null;
					$link_target = '_self';
					if ( $is_active && ! empty( $plugin['admin_url'] ) ) {
						$link_url = admin_url( $plugin['admin_url'] );
					} elseif ( $is_planned && ! empty( $plugin['marketing_url'] ) ) {
						$link_url = $plugin['marketing_url'];
						$link_target = '_blank';
					} elseif ( $is_active && ! empty( $plugin['plugin_file'] ) ) {
						// Try to construct default admin URL from slug.
						$link_url = admin_url( 'admin.php?page=' . $plugin['slug'] );
					}
					?>
					<div class="<?php echo esc_attr( $card_class ); ?>" style="<?php echo esc_attr( $card_style ); ?>" onmouseover="this.style.boxShadow='0 1px 3px rgba(0,0,0,.13)'" onmouseout="this.style.boxShadow='0 1px 1px rgba(0,0,0,.04)'">
						<div style="display: flex; align-items: flex-start; gap: 15px;">
							<div style="font-size: 32px; color: #50575e; flex-shrink: 0;">
								<span class="dashicons <?php echo esc_attr( $plugin['icon'] ); ?>"></span>
							</div>
							<div style="flex: 1; min-width: 0;">
								<h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: #1d2327;">
									<?php if ( $link_url ) : ?>
										<a href="<?php echo esc_url( $link_url ); ?>" target="<?php echo esc_attr( $link_target ); ?>" style="text-decoration: none; color: inherit;">
											<?php echo esc_html( $plugin['name'] ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $plugin['name'] ); ?>
									<?php endif; ?>
								</h3>
								<p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.5;">
									<?php echo esc_html( $plugin['description'] ); ?>
								</p>
								<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
									<?php if ( $is_active ) : ?>
										<span class="flux-suite-plugin-status" style="display: inline-flex; align-items: center; padding: 4px 8px; background: #00a32a; color: #fff; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
											<span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
											<?php echo esc_html__( 'Active', I18n::domain() ); ?>
										</span>
										<?php if ( $link_url ) : ?>
											<a href="<?php echo esc_url( $link_url ); ?>" class="button button-small" style="text-decoration: none;">
												<?php echo esc_html__( 'Manage', I18n::domain() ); ?>
											</a>
										<?php endif; ?>
									<?php elseif ( $is_planned ) : ?>
										<span class="flux-suite-plugin-status" style="display: inline-flex; align-items: center; padding: 4px 8px; background: #dba617; color: #fff; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
											<span class="dashicons dashicons-clock" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
											<?php echo esc_html__( 'Planned', I18n::domain() ); ?>
										</span>
										<?php if ( $link_url ) : ?>
											<a href="<?php echo esc_url( $link_url ); ?>" target="_blank" class="button button-small" style="text-decoration: none;">
												<?php echo esc_html__( 'Learn More', I18n::domain() ); ?>
												<span class="dashicons dashicons-external" style="font-size: 12px; width: 12px; height: 12px; margin-left: 4px; vertical-align: middle;"></span>
											</a>
										<?php endif; ?>
									<?php else : ?>
										<span class="flux-suite-plugin-status" style="display: inline-flex; align-items: center; padding: 4px 8px; background: #c3c4c7; color: #fff; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
											<span class="dashicons dashicons-dismiss" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
											<?php echo esc_html__( 'Inactive', I18n::domain() ); ?>
										</span>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render license page.
	 *
	 * Renders the React License page component.
	 * Scripts are enqueued via admin_enqueue_scripts hook when page is registered.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_license_page() {
		// Render the React app container
		// Scripts are enqueued automatically via admin_enqueue_scripts hook
		?>
		<div class="wrap">
			<div id="flux-plugins-common-license-app"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue license page scripts.
	 *
	 * Enqueues scripts only once, shared across all plugins.
	 * Uses WordPress action hooks to track enqueue state per request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function enqueue_license_page_scripts() {
		// Ensure scripts are enqueued only once (shared across all plugins).
		if ( did_action( 'flux_suite/menu_service/enqueue_license_scripts' ) ) {
			return;
		}

		/**
		 * Fires when license page scripts are being enqueued.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/menu_service/enqueue_license_scripts' );

		// Hook into admin_enqueue_scripts to enqueue license page scripts.
		add_action( 'admin_enqueue_scripts', [ $this, 'do_enqueue_license_scripts' ], 10 );
	}

	/**
	 * Enqueue license page scripts callback.
	 *
	 * Called by WordPress admin_enqueue_scripts hook.
	 * Only enqueues if the license page is actually registered.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function do_enqueue_license_scripts( $hook ) {
		// Only load on license page.
		if ( $hook !== 'flux-suite_page_' . self::LICENSE_PAGE_SLUG ) {
			return;
		}

		// Only enqueue if license page is registered
		if ( ! did_action( 'flux_suite/menu_service/register_license_page' ) ) {
			return;
		}

		// Get script URL from common library's own dist folder
		$script_url = $this->get_common_library_asset_url( 'js/dist/license-page.bundle.js' );
		
		if ( empty( $script_url ) ) {
			return;
		}

		// Get current user email
		$current_user = wp_get_current_user();
		$user_email = $current_user->ID ? $current_user->user_email : '';

		// Enqueue WordPress dependencies
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-i18n' );
		wp_enqueue_style( 'wp-components' );

		// Enqueue the license page script
		wp_enqueue_script(
			'flux-plugins-common-license-page',
			$script_url,
			[ 'wp-api-fetch', 'wp-element', 'wp-components', 'wp-i18n' ],
			'1.0.0',
			true
		);

		// Localize script with WordPress data
		wp_localize_script( 'flux-plugins-common-license-page', 'fluxPluginsCommon', [
			'apiUrl' => rest_url( 'flux-plugins-common/v1/' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'adminUrl' => admin_url(),
			'userEmail' => $user_email,
		] );
	}

	/**
	 * Get common library asset URL.
	 *
	 * Returns the URL to an asset in the common library's assets directory.
	 * The common library is self-contained and knows its own location.
	 * Uses the same pattern as CompatibilityService for consistency.
	 *
	 * @since 1.0.0
	 * @param string $asset_path Relative path from assets directory (e.g., 'js/dist/license-page.bundle.js').
	 * @return string Asset URL or empty string if not found.
	 */
	private function get_common_library_asset_url( $asset_path ) {
		// Get the common library directory path.
		// This file is at: vendor-prefixed/stratease/flux-plugins-common/src/Services/MenuService.php
		// Assets are at: vendor-prefixed/stratease/flux-plugins-common/src/assets/
		// Assets are now in src/ so Strauss will copy them

		// Get the URL using plugins_url - same pattern as CompatibilityService
		// Use the assets directory directly
		return plugins_url( 'src/assets/' . $asset_path, dirname( __DIR__ ) );
	}

	/**
	 * Render logs page.
	 *
	 * Renders the React Logs page component.
	 * Scripts are enqueued via admin_enqueue_scripts hook when page is registered.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_logs_page() {
		// Render the React app container
		// Scripts are enqueued automatically via admin_enqueue_scripts hook
		?>
		<div class="wrap">
			<div id="flux-plugins-common-logs-app"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue logs page scripts.
	 *
	 * Enqueues scripts only once, shared across all plugins.
	 * Uses WordPress action hooks to track enqueue state per request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function enqueue_logs_page_scripts() {
		// Ensure scripts are enqueued only once (shared across all plugins).
		if ( did_action( 'flux_suite/menu_service/enqueue_logs_scripts' ) ) {
			return;
		}

		/**
		 * Fires when logs page scripts are being enqueued.
		 *
		 * @since 1.0.0
		 */
		do_action( 'flux_suite/menu_service/enqueue_logs_scripts' );

		// Hook into admin_enqueue_scripts to enqueue logs page scripts.
		add_action( 'admin_enqueue_scripts', [ $this, 'do_enqueue_logs_scripts' ], 10 );
	}

	/**
	 * Enqueue logs page scripts callback.
	 *
	 * Called by WordPress admin_enqueue_scripts hook.
	 * Only enqueues if the logs page is actually registered.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function do_enqueue_logs_scripts( $hook ) {
		// Only load on logs page.
		$expected_hook = 'flux-suite_page_' . self::LOGS_PAGE_SLUG;
		if ( $hook !== $expected_hook ) {
			// Debug: log the actual hook for troubleshooting
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'Flux Plugins Common: Logs page hook mismatch. Expected: %s, Got: %s', $expected_hook, $hook ) );
			}
			return;
		}

		// Only enqueue if logs page is registered
		if ( ! did_action( 'flux_suite/menu_service/register_logs_page' ) ) {
			return;
		}

		// Get script URL from common library's own dist folder
		$script_url = $this->get_common_library_asset_url( 'js/dist/logs-page.bundle.js' );
		
		if ( empty( $script_url ) ) {
			return;
		}

		// Enqueue WordPress dependencies
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-i18n' );
		wp_enqueue_style( 'wp-components' );

		// Enqueue the logs page script
		wp_enqueue_script(
			'flux-plugins-common-logs-page',
			$script_url,
			[ 'wp-api-fetch', 'wp-element', 'wp-components', 'wp-i18n' ],
			'1.0.0',
			true
		);

		// Localize script with WordPress data
		wp_localize_script( 'flux-plugins-common-logs-page', 'fluxPluginsCommon', [
			'apiUrl' => rest_url( 'flux-plugins-common/v1/' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'adminUrl' => admin_url(),
		] );
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
		echo '<div class="wrap"><h1>' . esc_html__( 'Settings', I18n::domain() ) . '</h1></div>';
	}
}
