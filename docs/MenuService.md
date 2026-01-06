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
- `flux_suite/common/menu_registered` - Fired when "Flux Suite" menu is registered
- `flux_suite/common/license_page_registered` - Fired when License page is registered
- `flux_suite/common/logs_page_registered` - Fired when Logs page is registered
- `flux_suite/common/settings_page_registered` - Fired when Settings page is registered

**Static property for settings tabs:**
- `MenuService::$settings_tabs` - Static array storing all registered settings tabs (shared across all plugin instances per request)

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

**Uses WordPress action hook:** `flux_suite/common/menu_registered`

### `register_submenu_page( $slug, $title, $callback, $capability = 'manage_options' )`

Registers a plugin-specific submenu page under "Flux Suite".

**Parameters:**
- `$slug` - Menu slug (unique identifier)
- `$title` - Menu title
- `$callback` - Callback function to render the page
- `$capability` - Required capability (default: 'manage_options')

### `register_license_page()`

Registers the shared License page. Only registers once, even if called by multiple plugins.

**Uses WordPress action hook:** `flux_suite/common/license_page_registered`

### `register_logs_page()`

Registers the shared Logs page. Only registers once, even if called by multiple plugins.

**Uses WordPress action hook:** `flux_suite/common/logs_page_registered`

### `register_settings_page( $tab_component, $tab_label )`

Registers a settings tab. Multiple plugins can call this to add their own tabs to the shared Settings page.

**Parameters:**
- `$tab_component` - React component name/path for the tab
- `$tab_label` - Tab label displayed in the UI

**Uses WordPress action hook and static property:**
- `flux_suite/common/settings_page_registered` - Fired when Settings page is registered
- `MenuService::$settings_tabs` - Static array storing all tabs (shared across all plugin instances per request)

### `get_settings_tabs()`

Retrieves all registered settings tabs from WordPress options.

**Returns:** Array of tab configurations with 'component' and 'label' keys.

## Constants

- `MENU_PRIORITY` - Fixed menu priority (30)
- `TOP_LEVEL_MENU_SLUG` - 'flux-suite'
- `LICENSE_PAGE_SLUG` - 'flux-suite-license'
- `SETTINGS_PAGE_SLUG` - 'flux-suite-settings'
- `LOGS_PAGE_SLUG` - 'flux-suite-logs'

## Best Practices

1. **Don't call registration methods multiple times** - They're idempotent, but unnecessary calls add overhead
2. **Use consistent slugs** - Plugin-specific slugs should be unique and descriptive
3. **Register settings tabs early** - Register tabs during plugin initialization to ensure they appear
4. **Trust the shared state** - The WordPress options ensure pages only appear once, even with multiple plugins

## Technical Notes

- Uses `get_site_option()` / `update_site_option()` for multisite compatibility
- All registration checks happen before WordPress's `admin_menu` action fires
- The service hooks into `admin_menu` at priority 30 (as specified)
- Menu slugs are constants to ensure consistency across plugins

