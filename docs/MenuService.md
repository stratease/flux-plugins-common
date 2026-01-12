# MenuService Documentation

## Overview

The `MenuService` provides centralized WordPress admin menu registration for the Flux Plugins suite. It ensures that shared pages (License, Logs, Settings) appear only once, even when multiple plugins register them.

## Architecture: WordPress Action Hooks for State Management

### The Problem

Since each plugin uses Strauss to namespace-prefix this library, each plugin has its own isolated copy of the library classes. For example:

- Plugin A: `FluxMedia\Common\Services\MenuService`
- Plugin B: `FluxOther\Common\Services\MenuService`

These are completely separate classes with separate singleton instances. This means:

- Singleton patterns don't work across plugins
- Instance variables don't persist across plugins
- Plugin A's `$license_page_registered = true` is invisible to Plugin B

### The Solution

The `MenuService` uses **WordPress action hooks** (`did_action()` / `do_action()`) to track registration state per request. This ensures all plugins share the same state, regardless of their namespace prefixing.

**WordPress action hooks used:**
- `flux_suite/menu_service/register_top_level_menu` - Fired when top-level menu is registered
- `flux_suite/menu_service/register_license_page` - Fired when License page is registered
- `flux_suite/menu_service/register_logs_page` - Fired when Logs page is registered
- `flux_suite/menu_service/register_settings_page` - Fired when Settings page is registered
- `flux_suite/menu_service/init_plugin_registry` - Fired when plugin registry is initialized

**Static properties (shared across all plugin instances per request):**
- `MenuService::$settings_tabs` - Array storing all registered settings tabs
- `MenuService::$registered_plugins` - Array storing all registered Flux Suite plugins

### Why This Works

WordPress action hooks are part of WordPress's core hook system and are accessible to all plugins, regardless of their namespace. This makes WordPress itself the shared service layer that coordinates state across all plugin instances per request.

### Why Action Hooks Instead of Options?

Using action hooks is superior to WordPress options for this use case because:
- **Per-request scope**: Menu registration only needs to happen once per request, not persist across requests
- **No database overhead**: Action hooks are in-memory, avoiding unnecessary database reads/writes
- **Native WordPress pattern**: Uses WordPress's built-in hook system, which is the standard way to track request-scoped operations
- **Cleaner code**: `did_action()` / `do_action()` is more idiomatic than option checks

## Usage

### Basic Initialization

The `MenuService` is automatically initialized by `FluxPlugins::init()`. You typically don't need to call it directly.

### Register Plugin-Specific Submenu

```php
use FluxPlugins\Common\Services\MenuService;

$menu_service = MenuService::get_instance();
$menu_service->register_submenu_page(
    'my-plugin-slug',
    __( 'My Plugin', 'my-plugin' ),
    [ $this, 'render_my_plugin_page' ],
    'manage_options'
);
```

### Register Settings Tab

```php
use FluxPlugins\Common\Services\MenuService;

$menu_service = MenuService::get_instance();
$menu_service->register_settings_page(
    'MyPluginSettingsTab', // React component name
    __( 'My Plugin Settings', 'my-plugin' ) // Tab label
);
```

## Methods

### `register_top_level_menu()`

Registers the "Flux Suite" top-level menu. This is automatically called by other registration methods and is idempotent (safe to call multiple times).

**Uses WordPress action hook:** `flux_suite/menu_service/register_top_level_menu`

### `register_submenu_page( $slug, $title, $callback, $capability = 'manage_options' )`

Registers a plugin-specific submenu page under "Flux Suite".

**Parameters:**
- `$slug` - Menu slug (unique identifier)
- `$title` - Menu title
- `$callback` - Callback function to render the page
- `$capability` - Required capability (default: 'manage_options')

### `register_license_page()`

Registers the shared License page. Only registers once, even if called by multiple plugins.

**Uses WordPress action hook:** `flux_suite/menu_service/register_license_page`

### `register_logs_page()`

Registers the shared Logs page. Only registers once, even if called by multiple plugins.

**Uses WordPress action hook:** `flux_suite/menu_service/register_logs_page`

### `register_settings_page( $tab_component, $tab_label )`

Registers a settings tab. Multiple plugins can call this to add their own tabs to the shared Settings page.

**Parameters:**
- `$tab_component` - React component name/path for the tab
- `$tab_label` - Tab label displayed in the UI

**Uses WordPress action hook and static property:**
- `flux_suite/menu_service/register_settings_page` - Fired when Settings page is registered
- `MenuService::$settings_tabs` - Static array storing all tabs (shared across all plugin instances per request)

### `get_settings_tabs()`

Retrieves all registered settings tabs from static property.

**Returns:** Array of tab configurations with 'component' and 'label' keys.

### `get_registered_plugins()`

Retrieves all registered Flux Suite plugins, checks their activation status, and sorts them appropriately.

**Returns:** Array of plugin configurations with activation status. Plugins are sorted: inactive first, then planned, then active (at bottom).

**Plugin Status:**
- **Active**: Plugin has a `plugin_file` and `is_plugin_active()` returns true
- **Planned**: Plugin has no `plugin_file` (not yet developed)
- **Inactive**: Plugin has a `plugin_file` but is not active

### `render_top_level_page()`

Renders the Flux Suite overview page displaying all registered plugins in an attractive grid layout. This is the default page shown when clicking the "Flux Suite" top-level menu.

**Features:**
- Responsive CSS grid layout
- Color-coded status indicators (green for active, yellow for planned, gray for inactive)
- Links to plugin admin pages (for active plugins) or marketing pages (for planned plugins)
- Automatic sorting with active plugins at the bottom

## Constants

- `MENU_PRIORITY` - Fixed menu priority (30)
- `TOP_LEVEL_MENU_SLUG` - 'flux-suite'
- `LICENSE_PAGE_SLUG` - 'flux-suite-license'
- `SETTINGS_PAGE_SLUG` - 'flux-suite-settings'
- `LOGS_PAGE_SLUG` - 'flux-suite-logs'

## Flux Suite Plugin Registry

The `MenuService` includes a centralized plugin registry for marketing all Flux Suite plugins. This feature:

- **Centralized Management**: All plugins are registered in `MenuService::init_plugin_registry()` for marketing purposes only
- **Automatic Detection**: Active plugins are automatically detected using WordPress's `is_plugin_active()` function
- **Smart Sorting**: Plugins are sorted by status - inactive first, then planned, then active (at bottom)
- **Status Indicators**: Plugins show as "Active", "Planned", or "Inactive" with appropriate styling
- **Marketing Links**: Planned plugins link to marketing pages on fluxplugins.com

### Important Notes

- **Individual plugins should NOT register themselves** - All registrations are managed centrally in `MenuService`
- The registry is initialized automatically during `MenuService::init()`
- Plugins with a `plugin_file` are checked for activation status
- Plugins without a `plugin_file` are marked as "Planned"
- The top-level "Flux Suite" menu page displays all registered plugins

## Best Practices

1. **Don't call registration methods multiple times** - They're idempotent, but unnecessary calls add overhead
2. **Use consistent slugs** - Plugin-specific slugs should be unique and descriptive
3. **Register settings tabs early** - Register tabs during plugin initialization to ensure they appear
4. **Trust the shared state** - WordPress action hooks ensure pages only appear once, even with multiple plugins
5. **Don't register plugins individually** - All plugin registrations are managed centrally in `MenuService::init_plugin_registry()`

## Technical Notes

- Uses WordPress action hooks (`did_action()` / `do_action()`) for per-request state management
- All registration checks happen before WordPress's `admin_menu` action fires
- The service hooks into `admin_menu` at priority 30 (as specified)
- Menu slugs are constants to ensure consistency across plugins
- Plugin registry uses static properties shared across all plugin instances per request

