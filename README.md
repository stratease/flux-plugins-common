# Flux Plugins Common

Shared library for the Flux Plugins suite providing common services, React components, and infrastructure.

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
- `flux_suite/menu_service/init_plugin_registry` - Fired when plugin registry is initialized

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

**Important:** The built bundle files (`src/assets/js/dist/*.bundle.js`) **must be committed** to the repository. These files are required when the library is installed via Composer/Strauss, as plugins need access to the pre-built bundles without requiring a build step. Assets are stored in `src/assets/` so Strauss will copy them to the vendor-prefixed location.

For development with watch mode:

```bash
npm run dev
```

## Quick Start

In your plugin's main file, initialize the common library:

```php
use FluxPlugins\Common\FluxPlugins;

// Initialize the common library
FluxPlugins::init('your-plugin-slug', '1.0.0', 'your-plugin-text-domain');
```

This single call will:
- Ensure account ID exists
- Register the "Flux Suite" top-level menu (if not already registered)
- Initialize the Flux Suite plugin registry (marketing overview page)
- Register License page (shared across all plugins)
- Register Logs page (shared across all plugins)
- Initialize compatibility validation system for your plugin
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
FluxPlugins::init('your-plugin-slug', '1.0.0', 'your-plugin-text-domain');

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
    FluxPlugins::init( YOUR_PLUGIN_PLUGIN_SLUG, YOUR_PLUGIN_VERSION, 'your-plugin-text-domain' );
    
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
        <div id="your-plugin-app"></div>
        <?php
    }
}
```

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
    FluxPlugins::init( YOUR_PLUGIN_PLUGIN_SLUG, YOUR_PLUGIN_VERSION, 'your-plugin-text-domain' );
    
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
