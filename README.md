# Flux Plugins Common

Shared library for the Flux Plugins suite providing common services, React components, and infrastructure.

## Important: Externally Managed Source

This repository (`stratease/flux-plugins-common`) is the **single source of truth** for common library code.

- Consuming plugins install this library via Composer and then **Strauss namespace-prefix** it into `vendor-prefixed/`.
- Do **not** edit the copied/prefixed files inside individual plugin repositories. Those changes will be overwritten on the next Composer/Strauss run.
  - Edit source here, then update consuming plugin dependency.

### Reference Implementation (Flux Media Optimizer)

- Plugin bootstrap example: `wp-content/plugins/flux-media-optimizer/flux-media-optimizer.php`
- Composer + Strauss setup example: `wp-content/plugins/flux-media-optimizer/composer.json`

## Overview

This library provides reusable components for all Flux Plugins, including:

- **Main Initialization Service** (`FluxPlugins`) - Single entry point for plugin initialization
- **Menu System** (`MenuService`) - Centralized WordPress admin menu registration with Flux Suite plugin registry
- **Compatibility Service** (`CompatibilityService`) - Plugin/API version compatibility validation and notices
- **Account ID Service** - Shared account UUID management
- **I18n Service** - Centralized internationalization and text domain management
- **Logger Service** - Standardized logging with database handler
- **External API Client** - Shared API client for license validation and compatibility checks
- **React Components** - Shared UI components and theme

## Important: Namespace Prefixing and State Management

**Critical Design Decision:** Since each plugin uses Strauss to namespace-prefix this library (e.g., `FluxMedia\Common\` vs `FluxOther\Common\`), each plugin effectively has its own isolated copy of the library. This means:

- **Singleton patterns don't work across plugins** - Each plugin's singleton instance is separate
- **Instance variables don't persist across plugins** - Plugin A's state is invisible to Plugin B

**Solution:** The library uses **WordPress action hooks** (`did_action()` / `do_action()`) to track shared state per request across all plugins. This ensures:

- Menu registration state is shared (menu only appears once even if multiple plugins register it)
- Plugin registry is initialized once (centralized marketing overview)
- License page registration is shared (appears once)
- Logs page registration is shared (appears once)
- Settings page tabs are collected from all plugins
- Compatibility assets are only enqueued once

This approach leverages WordPress's built-in hook system as the shared service layer, ensuring consistent behavior regardless of how many plugins use the library or how they namespace it. Using action hooks is cleaner than WordPress options because:
- It's per-request, not persistent (no database overhead)
- It uses WordPress's native hook system
- It's more efficient for request-scoped operations

### Hook Naming Convention

**All hooks MUST follow the WordPress standard pattern:** `{plugin_namespace}/{class_name}/{method_name}` with an optional `/{operation}` suffix for more refined callbacks. This makes hooks traceable to the class and method that fires them, improving code discoverability and maintainability.

**Pattern Structure:**
- **Plugin Namespace**: The plugin's slug in snake_case (e.g., `flux_suite` for common library, `flux_ai_alt_creator` for Alt Text & Accessibility Audit plugin, `flux_media_optimizer` for Media Optimizer plugin).
- **Class Name**: The class name in snake_case (e.g., `MenuService` → `menu_service`, `AltTextProvider` → `alt_text_provider`).
- **Method Name**: The method name in snake_case (e.g., `generate_alt_text`, `register_routes`).
- **Operation (Optional)**: A specific operation or state within the method (e.g., `before`, `after`, `batch_size`).

**Examples:**

**Common Library Hooks (flux_suite namespace):**
- `flux_suite/menu_service/register_top_level_menu` - Fired when top-level menu is registered
- `flux_suite/menu_service/register_license_page` - Fired when License page is registered
- `flux_suite/menu_service/register_logs_page` - Fired when Logs page is registered
- `flux_suite/menu_service/register_settings_page` - Fired when Settings page is registered
- `flux_suite/menu_service/init_plugin_registry` - Fired when plugin registry is initialized
- `flux_suite/compatibility_service/assets_enqueued` - Fired when compatibility assets are enqueued

**Plugin-Specific Hooks (plugin namespace):**
- `flux_ai_alt_creator/alt_text_provider/process_attachment` - Action to process an attachment for alt text generation
- `flux_ai_alt_creator/alt_text_api_service/generate_alt_text` - Filter to intercept alt text generation before default processing
- `flux_ai_alt_creator/openai_service/generate_alt_text/before` - Fired before generating alt text via OpenAI
- `flux_ai_alt_creator/openai_service/generate_alt_text/after` - Fired after generating alt text via OpenAI
- `flux_ai_alt_creator/media_scanner/scan/before` - Fired before scanning for media files
- `flux_ai_alt_creator/media_scanner/scan/after` - Fired after scanning for media files
- `flux_ai_alt_creator/admin_controller/get_tabs` - Filter to register additional tabs on the admin page

**Why This Convention Matters:**
1. **Traceability**: When you see a hook name, you can immediately identify which class and method fires it
2. **Consistency**: All Flux Plugins use the same naming pattern, making code easier to understand
3. **Discoverability**: Developers can easily find where hooks are fired by searching for the hook name
4. **Namespace Safety**: Plugin-specific hooks won't conflict with hooks from other plugins or the common library

**Important Rules:**
- **Always use snake_case** for all parts of the hook name (class names, method names, operations)
- **Use descriptive operation suffixes** when filtering at specific points in a method (e.g., `/before`, `/after`, `/batch_size`)
- **Never use old-style hook names** with underscores between plugin namespace and hook description (e.g., `flux_ai_alt_creator_generate_alt_text` ❌)
- **Always convert class names to snake_case** (PascalCase → snake_case: `AltTextProvider` → `alt_text_provider`)

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

## WordPress.org packaging and bundled library

Plugins that ship this library (via Composer and Strauss into `vendor-prefixed/`) must keep **release artifacts** aligned with [WordPress.org plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).

**Distribution copy:** Shared build tooling lives in this repo:

- [`bin/build-plugin.sh`](bin/build-plugin.sh) — rsync from the plugin working tree into `wporg/trunk/` (or a temp tree) for zips and SVN.
- [`bin/plugin-dist-rsync-excludes.txt`](bin/plugin-dist-rsync-excludes.txt) — **single source of truth** for rsync `--exclude` patterns (dev dependencies, tests, maps, PHPUnit files, `audit-*.md`, etc.). Edit this file when a new dev-only path must never ship.
- [`bin/verify-plugin-distribution.sh`](bin/verify-plugin-distribution.sh) — optional gate: performs the same filtered copy to a temp directory and fails if `phpunit.xml.dist` or `audit-*.md` appear under the simulated distribution tree.

Run the verifier from a plugin root (example):

```bash
/path/to/flux-plugins-common/bin/verify-plugin-distribution.sh /path/to/your-plugin
```

**Guideline checklist (high level, not legal advice):**

- Declare **GPL-2.0-or-later** (or compatible) for the **whole** plugin, including prefixed vendor code, in the plugin header and `readme.txt`.
- Document **external services** (API endpoints, data sent) accurately in `readme.txt` if the shipped code calls out to third-party or first-party hosted services.
- Do not ship **development-only** files (tests, source maps, audit notes, local tooling) in the WordPress.org zip; rely on `plugin-dist-rsync-excludes.txt` and the verifier before tagging a release.

## Building Assets

The shared library includes JavaScript assets that need to be built:

```bash
cd flux-plugins-common
npm install
npm run build
```

**Important:** The built bundle files (`src/assets/js/dist/*.bundle.js`) **must be committed** to the repository. These files are required when the library is installed via Composer/Strauss, as plugins need access to the pre-built bundles without requiring a build step. Assets are stored in `src/assets/` so Strauss will copy them to the vendor-prefixed location.

For development with watch mode:

```bash
npm run dev
```

## Asset Management

### Asset URL Configuration

The common library requires a URL path to the plugin's common assets folder. This allows assets to be served from the plugin's own directory instead of the vendor-prefixed location, providing cleaner URLs and easier debugging.

**Initialization:**

```php
use YourPlugin\FluxPlugins\Common\FluxPlugins;

// Initialize with common assets URL
FluxPlugins::init(
    'your-plugin-slug',
    '1.0.0',
    'your-text-domain',
    plugin_dir_url( __FILE__ ) . 'src/assets/common/' // Common assets URL
);
```

The common assets URL should point to a directory within your plugin where the common library assets will be copied. This URL is passed to `MenuService` and `CompatibilityService` for enqueuing scripts and styles.

### Composer Script Setup

Each plugin must copy common library assets from `vendor/` to `src/assets/common/` **before** Strauss runs. Add this to your `composer.json`:

```json
{
    "scripts": {
        "copy-common-assets": [
            "sh -c 'if [ -d vendor/stratease/flux-plugins-common/src/assets ]; then mkdir -p src/assets/common && cp -r vendor/stratease/flux-plugins-common/src/assets/* src/assets/common/ && echo \"✅ Copied common library assets to src/assets/common/\"; else echo \"⚠️  Common library assets not found in vendor/\"; fi'"
        ],
        "prefix-namespaces": [
            "@copy-common-assets",
            "sh -c 'test -f ./bin/strauss.phar || curl -o bin/strauss.phar -L -C - https://github.com/BrianHenryIE/strauss/releases/latest/download/strauss.phar'",
            "@php bin/strauss.phar",
            "@composer dump-autoload",
            "@fix-bin-wrappers"
        ]
    }
}
```

**Important:** The `copy-common-assets` script must run **BEFORE** `prefix-namespaces` (Strauss) so that assets are available in the plugin's directory structure before Strauss processes the vendor files.

### Directory Structure

After running `composer install` or `composer update`, your plugin should have this structure:

```
your-plugin/
├── src/
│   └── assets/
│       └── common/          # Copied from vendor before Strauss
│           ├── js/
│           │   ├── dist/    # Built bundles
│           │   └── src/     # Source files
│           └── images/      # Image assets
├── vendor/                  # Original Composer dependencies
└── vendor-prefixed/         # Strauss-prefixed dependencies
```

The `src/assets/common/` directory contains all common library assets and is used for enqueuing scripts and styles. This directory should be included in your plugin's build/deployment process.

### Backwards Compatibility

If no common assets URL is provided during initialization, the library will fall back to using the vendor-prefixed path for asset enqueuing. This ensures backwards compatibility with existing plugins that haven't been updated yet.

## Quick Start

In your plugin's main file, initialize the common library:

```php
use YourPlugin\FluxPlugins\Common\FluxPlugins;

// Initialize the common library with common assets URL
FluxPlugins::init(
    'your-plugin-slug',
    '1.0.0',
    'your-plugin-text-domain',
    plugin_dir_url( __FILE__ ) . 'src/assets/common/' // Common assets URL
);
```

This single call will:
- Ensure account ID exists
- Register the "Flux Suite" top-level menu (if not already registered)
- Initialize the Flux Suite plugin registry (marketing overview page)
- Register License page (shared across all plugins)
- Register Logs page (shared across all plugins)
- Initialize compatibility validation system for your plugin
- Configure asset enqueuing to use the provided common assets URL
- Set up internationalization with your plugin's text domain

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
FluxPlugins::init('your-plugin-slug', '1.0.0', 'your-plugin-text-domain', plugin_dir_url( __FILE__ ) . 'src/assets/common/');

// Access services using factory methods (no plugin slug needed)
$validator = CompatibilityService::get_validator(); // Uses current plugin from init()
$menu_service = MenuService::get_instance();
$compatibility_service = CompatibilityService::get_instance();

// If you need a specific plugin's validator (rare), you can still pass the slug
$other_validator = CompatibilityService::get_validator('other-plugin-slug');
```

**Key Principle:** Once you call `FluxPlugins::init($plugin_slug, $version, $text_domain)`, you don't need to pass the plugin slug again when accessing services. The services track the current plugin internally, reducing moving parts and improving maintainability.

## Compatibility System

The compatibility system validates plugin/API version compatibility and displays notices. Each plugin gets its own validator and notice handler, ensuring notices from multiple plugins can display independently.

**Plugin-specific namespacing:**
- AJAX actions: `flux_plugins_compatibility_dismiss_{plugin_slug}`
- Transient prefixes: `flux_plugins_compatibility_dismissed_{plugin_slug}_`
- Cache options: `flux_plugins_compatibility_cache_{plugin_slug}`

**Shared assets:**
- JavaScript is only enqueued once across all plugins
- Uses `did_action()` to ensure no duplicate enqueuing

## Flux Suite Plugin Registry

The `MenuService` includes a centralized plugin registry for marketing all Flux Suite plugins. This registry:

- **Centralized Management**: All plugins are registered in `MenuService::init_plugin_registry()` for marketing purposes
- **Automatic Detection**: Active plugins are automatically detected using WordPress's `is_plugin_active()` function
- **Smart Sorting**: Plugins are sorted by status - inactive first, then planned, then active (at bottom)
- **Status Indicators**: Plugins show as "Active", "Planned", or "Inactive" with appropriate styling
- **Marketing Links**: Planned plugins link to marketing pages on fluxplugins.com

### How It Works

1. Plugins are hard-coded in `MenuService::init_plugin_registry()` during initialization
2. When a plugin has a `plugin_file` defined, the system checks if it's active
3. Active plugins automatically show as "Active" and sort to the bottom
4. Plugins without a `plugin_file` are marked as "Planned" and link to marketing pages
5. The top-level "Flux Suite" menu page displays all plugins in an attractive grid layout

### Adding New Plugins

To add a new plugin to the registry, edit `MenuService::init_plugin_registry()`:

```php
// For an active plugin:
$this->add_plugin_to_registry(
    'flux-new-plugin',
    __( 'New Plugin', I18n::domain() ),
    __( 'Description of the new plugin.', I18n::domain() ),
    'flux-new-plugin/flux-new-plugin.php', // Plugin file path
    'admin.php?page=flux-new-plugin',        // Admin URL
    'https://fluxplugins.com/new-plugin',    // Marketing URL
    'dashicons-admin-generic'                // Icon
);

// For a planned plugin:
$this->add_plugin_to_registry(
    'flux-planned-plugin',
    __( 'Planned Plugin', I18n::domain() ),
    __( 'Description of the planned plugin.', I18n::domain() ),
    null, // No plugin file = Planned
    null,
    'https://fluxplugins.com/planned-plugin',
    'dashicons-admin-generic'
);
```

**Important**: Individual plugins should NOT register themselves. All registrations are managed centrally in `MenuService` for marketing purposes only.

## Setting Up a New Plugin

This guide walks through integrating a new Flux Plugin with the common library, using Flux Media Optimizer as a reference implementation.

### PHP Initialization

#### 1. Main Plugin File Setup

In your plugin's main PHP file (e.g., `your-plugin.php`), initialize the common library:

```php
<?php
/**
 * Plugin Name: Your Plugin Name
 * ...
 */

use YourPlugin\FluxPlugins\Common\FluxPlugins;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'YOUR_PLUGIN_VERSION', '1.0.0' );
define( 'YOUR_PLUGIN_PLUGIN_SLUG', 'your-plugin-slug' );

// Load Composer autoloader
if ( file_exists( __DIR__ . '/vendor/autoload.php' )
    && file_exists( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/vendor-prefixed/autoload.php';
} else {
    add_action( 'admin_notices', 'your_plugin_composer_notice' );
    return;
}

// Initialize the plugin
add_action( 'plugins_loaded', 'your_plugin_init' );

/**
 * Initialize the plugin
 *
 * @since 1.0.0
 */
function your_plugin_init() {
    // Initialize Flux Plugins common library
    // This handles account ID, menu setup, REST API routes, and required pages
    FluxPlugins::init( YOUR_PLUGIN_PLUGIN_SLUG, YOUR_PLUGIN_VERSION, 'your-plugin-text-domain', YOUR_PLUGIN_PLUGIN_URL . 'src/assets/common/' );
    
    // Initialize your plugin's main class
    $your_plugin = new YourPlugin\App\Plugin();
    $your_plugin->init();
}
```

#### 2. Register Menu Pages

In your plugin's main class (e.g., `app/Plugin.php`), register menu pages using `MenuService`:

```php
use YourPlugin\FluxPlugins\Common\Services\MenuService;

class Plugin {
    public function init() {
        if ( is_admin() ) {
            add_action( 'init', [ $this, 'register_menu_pages' ], 10 );
        }
        
        // ... other initialization
    }
    
    /**
     * Register menu pages
     *
     * @since 1.0.0
     */
    public function register_menu_pages() {
        $menu_service = MenuService::get_instance();
        
        // Register License page (optional - only if your plugin needs license management)
        $menu_service->register_license_page();
        
        // Register your plugin-specific submenu page
        $menu_service->register_submenu_page(
            'your-plugin-slug',                    // Page slug
            __( 'Your Plugin', 'your-plugin' ),    // Page title
            [ $this, 'render_main_page' ],         // Callback
            'manage_options',                       // Capability
            1                                       // Placement (1 = first submenu)
        );
    }
    
    /**
     * Render main admin page
     *
     * @since 1.0.0
     */
    public function render_main_page() {
        ?>
        <div class="wrap">
            <span class="wp-header-end"></span>
            <div id="your-plugin-app"></div>
        </div>
        <?php
    }
}
```

**Admin notices and `.wrap`:** Always render a `<div class="wrap">` for submenu callbacks that load a full admin screen, and put **`<span class="wp-header-end"></span>` immediately before** your React (or other) mount element. WordPress core uses that sentinel when relocating admin notices; omitting it causes notices (including Flux Suite license messages) to anchor against the first heading inside your app and appear **inside** the React layout instead of above it.

#### 3. Register REST API Routes

Register your plugin's REST API routes in your plugin class:

```php
use YourPlugin\App\Http\Controllers\YourController;

class Plugin {
    public function init() {
        // ... other initialization
        
        $this->init_rest_api();
    }
    
    /**
     * Initialize REST API
     *
     * @since 1.0.0
     */
    private function init_rest_api() {
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }
    
    /**
     * Register REST API routes
     *
     * @since 1.0.0
     */
    public function register_rest_routes() {
        $controller = new YourController();
        $controller->register_routes();
    }
}
```

**Important:** REST API routes from the common library (License, Account ID, Logs) are automatically registered by `FluxPlugins::init()`. You only need to register your plugin-specific routes.

#### 4. Using the Logger Service

The Logger service is automatically initialized by `FluxPlugins::init()` with your plugin slug. You can access it anywhere in your plugin:

```php
use YourPlugin\FluxPlugins\Common\Logger\Logger;

class Plugin {
    private $logger;
    
    public function init() {
        // Get logger instance (uses plugin slug set during FluxPlugins::init())
        $this->logger = Logger::get_instance();
        
        // Use logger in your code
        $this->logger->debug( 'Plugin initialized' );
        $this->logger->info( 'Processing started', [ 'item_id' => 123 ] );
        $this->logger->warning( 'Deprecated feature used' );
        $this->logger->error( 'Operation failed', [ 'error_code' => 'E001' ] );
    }
}
```

**Important:** The Logger is initialized early in `FluxPlugins::init()`, so it's available immediately after calling `FluxPlugins::init()`. The plugin slug is automatically set from the `$plugin_slug` parameter you pass to `FluxPlugins::init()`, so all log entries will be tagged with your plugin's slug.

**Available Methods:**
- `Logger::get_instance()` - Get the singleton logger instance
- `$logger->debug( $message, $context = [] )` - Log debug messages
- `$logger->info( $message, $context = [] )` - Log informational messages
- `$logger->warning( $message, $context = [] )` - Log warnings
- `$logger->error( $message, $context = [] )` - Log errors
- `$logger->critical( $message, $context = [] )` - Log critical errors
- `$logger->alert( $message, $context = [] )` - Log alerts
- `$logger->emergency( $message, $context = [] )` - Log emergencies
- `$logger->log( $level, $message, $context = [] )` - Log with custom level

**Registering the Logs Page:**

To make logs viewable in the WordPress admin, register the logs page:

```php
use YourPlugin\FluxPlugins\Common\Services\MenuService;

class Plugin {
    public function register_menu_pages() {
        $menu_service = MenuService::get_instance();
        
        // Register License page (optional)
        $menu_service->register_license_page();
        
        // Register Logs page (optional - shows logs from all Flux Plugins)
        $menu_service->register_logs_page();
    }
}
```

#### 5. Enqueue Admin Scripts

Enqueue your React app and localize script data:

```php
class Plugin {
    public function init() {
        // ... other initialization
        
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }
    
    /**
     * Enqueue admin scripts
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only load on your plugin's admin pages
        if ( strpos( $hook, 'your-plugin-slug' ) === false ) {
            return;
        }
        
        // Enqueue your React app bundle
        wp_enqueue_script(
            'your-plugin-admin',
            plugin_dir_url( __FILE__ ) . 'assets/js/dist/admin.bundle.js',
            [ 'wp-api-fetch', 'wp-element', 'wp-components', 'wp-i18n' ],
            YOUR_PLUGIN_VERSION,
            true
        );
        
        // Localize script with WordPress data
        wp_localize_script( 'your-plugin-admin', 'yourPluginAdmin', [
            'apiUrl' => rest_url( 'your-plugin-slug/v1/' ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'adminUrl' => admin_url(),
            'pluginUrl' => YOUR_PLUGIN_PLUGIN_URL,
        ] );
        
        // Enqueue WordPress admin styles
        wp_enqueue_style( 'wp-components' );
    }
}
```

### React/JavaScript Setup

#### 1. Webpack Configuration

Create a `webpack.config.js` in your plugin that extends the common library's base config:

```javascript
const path = require('path');
const { createBaseWebpackConfig } = require('../../flux-plugins-common/webpack.config.helpers');

// Find flux-plugins-common directory
function findFluxPluginsCommonDir() {
  const possiblePaths = [
    path.resolve(__dirname, '../../flux-plugins-common'),
    path.resolve(__dirname, 'vendor-prefixed/stratease/flux-plugins-common'),
  ];
  
  for (const possiblePath of possiblePaths) {
    if (require('fs').existsSync(possiblePath) && 
        require('fs').existsSync(path.join(possiblePath, 'webpack.config.helpers.js'))) {
      return possiblePath;
    }
  }
  return path.resolve(__dirname, '../../flux-plugins-common');
}

const commonLibDir = findFluxPluginsCommonDir();
const baseConfig = createBaseWebpackConfig({
  pluginDir: __dirname,
  pluginSlug: 'your-plugin-slug',
});

module.exports = {
  ...baseConfig,
  entry: {
    ...baseConfig.entry,
    admin: './assets/js/src/admin/index.js',
  },
  output: {
    ...baseConfig.output,
    path: path.resolve(__dirname, 'assets/js/dist'),
    filename: '[name].bundle.js',
    clean: true,
  },
  resolve: {
    ...baseConfig.resolve,
    alias: {
      ...baseConfig.resolve.alias,
      '@your-plugin-slug': path.resolve(__dirname, 'assets/js/src'),
      '@flux-plugins-common': path.join(commonLibDir, 'src/assets/js/src'),
      '@flux-plugins-common/images': path.join(commonLibDir, 'src/assets/images'),
    },
  },
};
```

#### 2. React App Structure

Create your main React app component:

```javascript
// assets/js/src/admin/index.js
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

const container = document.getElementById('your-plugin-app');
if (container) {
  const root = createRoot(container);
  root.render(<App />);
}
```

#### 3. Use Common Library Components

In your React app, use the shared components and providers:

```javascript
// assets/js/src/admin/App.js
import React from 'react';
import { HashRouter as Router, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { FluxAppProvider, PageLayout } from '@flux-plugins-common/components';
import { __ } from '@wordpress/i18n';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

const App = () => {
  return (
    <QueryClientProvider client={queryClient}>
      <FluxAppProvider>
        <Router>
          <PageLayout title={__('Your Plugin Name', 'your-plugin')} maxWidth="xl">
            {/* Your app content - tabs, pages, etc. */}
          </PageLayout>
        </Router>
      </FluxAppProvider>
    </QueryClientProvider>
  );
};

export default App;
```

**Key Points:**
- `FluxAppProvider` provides theme and baseline styles (required)
- `PageLayout` provides consistent branding and layout (required)
- Individual page components should NOT use `PageLayout` - it's only at the app level
- Theme is centralized in the common library - plugins cannot override it

#### 4. API Service Setup

Create an API service for your REST endpoints:

```javascript
// assets/js/src/services/api.js
import apiFetch from '@wordpress/api-fetch';

class ApiService {
  constructor() {
    this.namespace = 'your-plugin-slug/v1';
    
    const apiRoot = window.yourPluginAdmin?.apiUrl || '/wp-json/';
    apiFetch.use(apiFetch.createRootURLMiddleware(apiRoot));
  }
  
  async request(endpoint, options = {}) {
    // Prepend namespace if not already included
    let path = endpoint;
    if (!endpoint.startsWith(`/${this.namespace}/`) && !endpoint.startsWith(this.namespace)) {
      const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
      path = `/${this.namespace}${cleanEndpoint}`;
    }
    
    const defaultOptions = {
      path: path,
      method: 'GET',
      headers: {
        'X-WP-Nonce': window.yourPluginAdmin?.nonce || '',
        'Content-Type': 'application/json',
      },
    };
    
    const mergedOptions = {
      ...defaultOptions,
      ...options,
      headers: {
        ...defaultOptions.headers,
        ...(options.headers || {}),
      },
    };
    
    try {
      const response = await apiFetch(mergedOptions);
      if (response && typeof response === 'object' && response.success !== undefined) {
        return response.data;
      }
      return response;
    } catch (error) {
      throw error;
    }
  }
  
  // Your plugin-specific endpoints
  async getStatus() {
    return this.request('/status');
  }
}

export const apiService = new ApiService();
export default apiService;
```

### Available Services and Features

#### License Management

The common library provides shared license management. To use it:

**PHP:**
```php
use YourPlugin\FluxPlugins\Common\License\LicenseService;

// Get license key
$license_key = LicenseService::get_instance()->get_license_key();

// Check if license is valid
$is_valid = LicenseService::get_instance()->is_license_valid();
```

**React:**
```javascript
import { useLicense, useActivateLicense, useValidateLicense } from '@flux-plugins-common/hooks/useLicense';

const MyComponent = () => {
  const { data: licenseData } = useLicense();
  const activateMutation = useActivateLicense();
  
  // Use license data...
};
```

**Register License Page:**
```php
// In your Plugin::register_menu_pages() method
$menu_service = MenuService::get_instance();
$menu_service->register_license_page();
```

#### Account ID Service

Get the shared account ID for technical support:

**PHP:**
```php
use YourPlugin\FluxPlugins\Common\Account\AccountIdService;

$account_id = AccountIdService::get_instance()->get_account_id();
```

**React:**
```javascript
import { useAccountId } from '@flux-plugins-common/hooks/useLicense';

const MyComponent = () => {
  const { data: accountIdData } = useAccountId();
  const accountId = accountIdData?.account_id;
  
  // Display account ID for support...
};
```

#### REST API Endpoints

The common library automatically registers these REST endpoints:

- `GET /wp-json/flux-plugins-common/v1/license` - Get license information
- `POST /wp-json/flux-plugins-common/v1/license/activate` - Activate license key
- `POST /wp-json/flux-plugins-common/v1/license/validate` - Validate current license
- `GET /wp-json/flux-plugins-common/v1/account-id` - Get account ID

Your plugin's REST endpoints should use your own namespace (e.g., `your-plugin-slug/v1`).

### Complete Example: Plugin Initialization

Here's a complete example based on Flux Media Optimizer:

```php
<?php
/**
 * Plugin Name: Your Plugin Name
 * ...
 */

use YourPlugin\FluxPlugins\Common\FluxPlugins;
use YourPlugin\App\Plugin;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'YOUR_PLUGIN_VERSION', '1.0.0' );
define( 'YOUR_PLUGIN_PLUGIN_SLUG', 'your-plugin-slug' );
define( 'YOUR_PLUGIN_PLUGIN_FILE', __FILE__ );
define( 'YOUR_PLUGIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YOUR_PLUGIN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader
if ( file_exists( YOUR_PLUGIN_PLUGIN_DIR . 'vendor/autoload.php' )
    && file_exists( YOUR_PLUGIN_PLUGIN_DIR . 'vendor-prefixed/autoload.php' ) ) {
    require_once YOUR_PLUGIN_PLUGIN_DIR . 'vendor/autoload.php';
    require_once YOUR_PLUGIN_PLUGIN_DIR . 'vendor-prefixed/autoload.php';
} else {
    add_action( 'admin_notices', 'your_plugin_composer_notice' );
    return;
}

// Initialize the plugin
add_action( 'plugins_loaded', 'your_plugin_init' );

/**
 * Initialize the plugin
 *
 * @since 1.0.0
 */
function your_plugin_init() {
    // Initialize Flux Plugins common library
    // This single call handles:
    // - Account ID generation/retrieval
    // - "Flux Suite" top-level menu registration
    // - License page registration (shared)
    // - REST API route registration (shared)
    // - Compatibility validation system
    // - Internationalization setup
    FluxPlugins::init( YOUR_PLUGIN_PLUGIN_SLUG, YOUR_PLUGIN_VERSION, 'your-plugin-text-domain', YOUR_PLUGIN_PLUGIN_URL . 'src/assets/common/' );
    
    // Initialize your plugin's main class
    $your_plugin = new Plugin();
    $your_plugin->init();
}
```

### React Component Usage

#### Using PageLayout

`PageLayout` should only be used once at the app level, not in individual page components:

```javascript
// ✅ Correct - App.js
import { PageLayout } from '@flux-plugins-common/components';

const App = () => {
  return (
    <PageLayout title={__('Your Plugin', 'your-plugin')} maxWidth="xl">
      <Navigation />
      <Routes>
        <Route path="/overview" element={<OverviewPage />} />
      </Routes>
    </PageLayout>
  );
};

// ✅ Correct - Individual page (no PageLayout)
const OverviewPage = () => {
  return (
    <>
      <Grid container spacing={3}>
        {/* Page content */}
      </Grid>
    </>
  );
};
```

#### Using BrandIcon

If you need the Flux brand icon in a custom component:

```javascript
import { BrandIcon } from '@flux-plugins-common/components';

const MyComponent = () => {
  return <BrandIcon size={40} />;
};
```

### Theme

The common library provides a shared Material-UI theme. Plugins cannot override the theme - it's centralized for consistency.

**Usage:**
```javascript
import { FluxAppProvider } from '@flux-plugins-common/components';

// FluxAppProvider automatically applies the shared theme
const App = () => {
  return (
    <FluxAppProvider>
      {/* Your app */}
    </FluxAppProvider>
  );
};
```

### Webpack Alias Configuration

When building your plugin, ensure webpack aliases are configured correctly:

```javascript
// In your webpack.config.js
resolve: {
  alias: {
    '@flux-plugins-common': path.join(commonLibDir, 'src/assets/js/src'),
    '@flux-plugins-common/images': path.join(commonLibDir, 'src/assets/images'),
    '@your-plugin-slug': path.resolve(__dirname, 'assets/js/src'),
  },
}
```

**Important:** More specific aliases (like `@flux-plugins-common/images`) must come before general ones (like `@flux-plugins-common`) in the alias object.

## Documentation

See the `docs/` directory for detailed documentation on each component.

## License

GPL-2.0-or-later
