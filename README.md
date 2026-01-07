# Flux Plugins Common

Shared library for the Flux Plugins suite providing common services, React components, and infrastructure.

## Overview

This library provides reusable components for all Flux Plugins, including:

- **Main Initialization Service** (`FluxPlugins`) - Single entry point for plugin initialization
- **Menu System** (`MenuService`) - Centralized WordPress admin menu registration
- **Compatibility Service** (`CompatibilityService`) - Plugin/API version compatibility validation and notices
- **Account ID Service** - Shared account UUID management
- **Logger Service** - Standardized logging with database handler
- **External API Client** - Shared API client for license validation and compatibility checks
- **React Components** - Shared UI components and theme

## Important: Namespace Prefixing and State Management

**Critical Design Decision:** Since each plugin uses Strauss to namespace-prefix this library (e.g., `FluxMedia\Common\` vs `FluxOther\Common\`), each plugin effectively has its own isolated copy of the library. This means:

- **Singleton patterns don't work across plugins** - Each plugin's singleton instance is separate
- **Instance variables don't persist across plugins** - Plugin A's state is invisible to Plugin B

**Solution:** The library uses **WordPress action hooks** (`did_action()` / `do_action()`) to track shared state per request across all plugins. This ensures:

- Menu registration state is shared (menu only appears once even if multiple plugins register it)
- License page registration is shared (appears once)
- Logs page registration is shared (appears once)
- Settings page tabs are collected from all plugins
- Compatibility assets are only enqueued once

This approach leverages WordPress's built-in hook system as the shared service layer, ensuring consistent behavior regardless of how many plugins use the library or how they namespace it. Using action hooks is cleaner than WordPress options because:
- It's per-request, not persistent (no database overhead)
- It uses WordPress's native hook system
- It's more efficient for request-scoped operations

### Hook Naming Convention

All hooks follow the WordPress standard pattern: `{plugin_namespace}/{class_name}/{method_name}` with an optional `/{operation}` suffix for more refined callbacks. This makes hooks traceable to the class and method that fires them.

- **Plugin Namespace**: `flux_suite` - Identifies the Flux Plugins suite.
- **Class Name**: `menu_service` - The class responsible for the hook.
- **Method Name**: `register_top_level_menu` - The method within the class that fires the hook.
- **Operation (Optional)**: `registered` - A specific operation within the method.

**Menu Service Hooks:**
- `flux_suite/menu_service/register_top_level_menu` - Fired when top-level menu is registered
- `flux_suite/menu_service/register_license_page` - Fired when License page is registered
- `flux_suite/menu_service/register_logs_page` - Fired when Logs page is registered
- `flux_suite/menu_service/register_settings_page` - Fired when Settings page is registered

**Compatibility Service Hooks:**
- `flux_suite/compatibility_service/assets_enqueued` - Fired when compatibility assets are enqueued

This convention ensures consistent, predictable hook names across all Flux Plugins and makes it easy to identify which component and resource a hook belongs to.

## Constants

The shared library defines constants that can be overridden in `wp-config.php`:

### Constant Naming Convention

All constants for the Flux Plugins Common library follow this pattern:
`FLUX_PLUGINS_COMMON_{FEATURE}_{OPTION}`

- **Prefix**: `FLUX_PLUGINS_COMMON_` - Identifies constants belonging to the shared library
- **Feature**: The feature or service the constant affects (e.g., `EXTERNAL_SERVICE`, `DISABLE_CACHE`)
- **Option**: The specific option or setting (e.g., `URL`, `TIMEOUT`)

### Available Constants

- `FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL` - Base URL for the external API service (default: `'https://api.fluxplugins.com'`)
- `FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT` - Request timeout in seconds (default: `15`)
- `FLUX_PLUGINS_COMMON_DISABLE_CACHE` - Disable caching for compatibility checks (default: `false`)

All constants can be overridden in `wp-config.php` by defining them before the library loads.

Example:
```php
// In wp-config.php
define( 'FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL', 'http://api:8080' );
define( 'FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT', 360 );
define( 'FLUX_PLUGINS_COMMON_DISABLE_CACHE', true );
```

## Installation

Add this repository as a VCS dependency in your plugin's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:stratease/flux-plugins-common.git"
        }
    ],
    "require": {
        "stratease/flux-plugins-common": "@master"
    }
}
```

Then run `composer install`.

## Building Assets

The shared library includes JavaScript assets that need to be built:

```bash
cd flux-plugins-common
npm install
npm run build
```

For development with watch mode:

```bash
npm run dev
```

## Quick Start

In your plugin's main file, initialize the common library:

```php
use FluxPlugins\Common\FluxPlugins;

// Initialize the common library
FluxPlugins::init('your-plugin-slug', '1.0.0');
```

This single call will:
- Ensure account ID exists
- Register the "Flux Suite" top-level menu (if not already registered)
- Register License page (shared across all plugins)
- Register Logs page (shared across all plugins)
- Initialize compatibility validation system for your plugin
- Initialize logger with your plugin slug

## Factory Pattern

All services follow the singleton factory pattern for consistency and maintainability:

### Pattern Rules

1. **Factory methods belong to the class they initialize** - Don't create factory methods in other classes
2. **Use `get_instance()` for singleton services** - Standard pattern for accessing service instances
3. **No redundant parameters** - Once `FluxPlugins::init($plugin_slug, $version)` is called, services track the current plugin internally
4. **Consistent access patterns** - All services follow the same pattern for predictability

### Usage Examples

```php
// Initialize the library (once per plugin)
FluxPlugins::init('your-plugin-slug', '1.0.0');

// Access services using factory methods (no plugin slug needed)
$validator = CompatibilityService::get_validator(); // Uses current plugin from init()
$menu_service = MenuService::get_instance();
$compatibility_service = CompatibilityService::get_instance();

// If you need a specific plugin's validator (rare), you can still pass the slug
$other_validator = CompatibilityService::get_validator('other-plugin-slug');
```

**Key Principle:** Once you call `FluxPlugins::init($plugin_slug, $version)`, you don't need to pass the plugin slug again when accessing services. The services track the current plugin internally, reducing moving parts and improving maintainability.

## Compatibility System

The compatibility system validates plugin/API version compatibility and displays notices. Each plugin gets its own validator and notice handler, ensuring notices from multiple plugins can display independently.

**Plugin-specific namespacing:**
- AJAX actions: `flux_plugins_compatibility_dismiss_{plugin_slug}`
- Transient prefixes: `flux_plugins_compatibility_dismissed_{plugin_slug}_`
- Cache options: `flux_plugins_compatibility_cache_{plugin_slug}`

**Shared assets:**
- JavaScript is only enqueued once across all plugins
- Uses `did_action()` to ensure no duplicate enqueuing

## Documentation

See the `docs/` directory for detailed documentation on each component.

## License

GPL-2.0-or-later
