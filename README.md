# Flux Plugins Common

Shared library for the Flux Plugins suite providing common services, React components, and infrastructure.

## Overview

This library provides reusable components for all Flux Plugins, including:

- **Main Initialization Service** (`FluxPlugins`) - Single entry point for plugin initialization
- **Menu System** (`MenuService`) - Centralized WordPress admin menu registration
- **Account ID Service** - Shared account UUID management
- **Logger Service** - Standardized logging with database handler
- **External API Client** - Shared API client for license validation and compatibility checks
- **Compatibility System** - Plugin/API version compatibility validation
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

This approach leverages WordPress's built-in hook system as the shared service layer, ensuring consistent behavior regardless of how many plugins use the library or how they namespace it. Using action hooks is cleaner than WordPress options because:
- It's per-request, not persistent (no database overhead)
- It uses WordPress's native hook system
- It's more efficient for request-scoped operations

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
        "stratease/flux-plugins-common": "dev-main"
    }
}
```

Then run `composer install`.

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
- Initialize logger with your plugin slug

## Documentation

See the `docs/` directory for detailed documentation on each component.

## License

GPL-2.0-or-later

