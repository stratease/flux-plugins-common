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
- **Logger Service** - Suite logging without Monolog (`$wpdb` log table; `error_log()` for high severity). **Monolog and `psr/log` are deprecated and removed** from this library as of **v1.2.0**; see [Monolog and psr log deprecation and removal](#monolog-and-psr-log-deprecation-and-removal).
- **External API Client** - Shared API client for license validation and compatibility checks
- **React Components** - Shared UI components and theme

## Monolog and psr log deprecation and removal

**Status:** Relying on **Monolog** (and on **`psr/log`** only for this library’s logger) is **deprecated** for Flux Plugins Common and **removed** starting with **v1.2.0**.

| Phase | What it means |
|--------|----------------|
| **Deprecated** | Older tagged releases may still list `monolog/monolog` and `psr/log` in *this* package’s `composer.json`. Do not add new code that type-hints Monolog classes or treats `Logger` as `Psr\Log\LoggerInterface`; prefer the public `Logger` API only. |
| **Removed (v1.2.0+)** | This repository’s `composer.json` **no longer requires** Monolog or `psr/log`. The suite **`Logger`** and **`DatabaseHandler`** are implemented with **WordPress `$wpdb`**, **`error_log()`** for high-severity lines, and **no** Monolog pipeline. |

**What integrators must do after upgrading to v1.2.0+**

1. Bump the Composer dependency on `stratease/flux-plugins-common` to a release that includes this change.
2. Remove **`monolog/monolog`** and **`psr/log`** from your plugin’s own `require` if you only added them because this library used to need them.
3. Remove both packages from Strauss **`extra.strauss.packages`** when they were listed only for common.
4. Run **`composer update`** and your **prefix** pipeline (e.g. Strauss) so `vendor-prefixed/` does not ship stale prefixed Monolog code.

**What stays the same:** `FluxPlugins::init()` still initializes **`Logger`** early; **`Logger::get_instance()`** and the level methods (`debug`, `info`, …, `log`) are **unchanged** for callers. See [Using the Logger Service](#4-using-the-logger-service) for usage and implementation details.

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

### Webpack dev server port registry

Each plugin with a React admin bundle must use a **unique** `devServer.port` so multiple plugins can run webpack dev servers simultaneously. **This table is the single source of truth** — update it whenever you add a plugin or change a port.

| Port | Plugin slug | Directory | Status |
|------|-------------|-----------|--------|
| 3000 | `flux-media-optimizer` | `flux-media-optimizer` | In use |
| 3001 | `flux-ai-gutenberg-page-builder` | `flux-ai-gutenberg-page-builder` | Reserved (no `devServer` block yet) |
| 3002 | `flux-ai-media-alt-creator` | `flux-ai-media-alt-creator` | In use |
| 3003 | `flux-ai-media-alt-creator-pro` | `flux-ai-media-alt-creator-pro` | In use |
| 3004 | `flux-one` | `flux-one-command-bar` | In use |
| 3005 | `flux-fixer` | `flux-fixer` | In use |
| 3006 | `flux-cta` | `flux-cta` | In use |
| 3007 | `flux-blog-audit` | `flux-blog-audit` | In use |
| 3000 | `flux-unused-media-cleaner` | `flux-unused-media-cleaner` | **Conflict** with Media Optimizer — reassign to 3008 |

**Next available port:** 3008 (verify the table first).

**Plugins without a webpack dev server** (no port assignment):

- `flux-accessibility-audit-scanner`
- `flux-form-accessibility-audit-scanner`
- `flux-image-optimization-audit-scanner`
- `flux-license-renewal-manager`

`flux-plugins-common` uses `npm run dev` (watch mode) without a dev server — no port row.

**When adding a new plugin:**

1. Pick the next unused port from the table (currently **3008**).
2. Set `devServer.port` in the plugin's `webpack.config.js`.
3. Add a row to this table in this README.
4. Set `FLUX_{PREFIX}_DEV_SCRIPT_BASE` in local `wp-config.php` to match (e.g. `http://localhost:3007`).
5. Do **not** use port **3000** without checking the table — it is webpack's default and is already assigned (and collides with Unused Media Cleaner).

### Optional local dev script base (wp-config only)

React admin bundles ship as built files under `assets/js/dist/`. For local webpack hot reload, each plugin may support an **optional** constant defined **only** in the site's `wp-config.php` (never in plugin bootstrap or release zips). See [Webpack dev server port registry](#webpack-dev-server-port-registry) for assigned ports.

**Naming convention:** `FLUX_{PLUGIN_PREFIX}_DEV_SCRIPT_BASE`

Examples:

- `FLUX_ONE_DEV_SCRIPT_BASE` — [flux-one-command-bar](https://github.com/stratease/flux-one-command-bar) (reference implementation)
- `FLUX_BLOG_AUDIT_DEV_SCRIPT_BASE` — flux-blog-audit
- `FLUX_MEDIA_OPTIMIZER_DEV_SCRIPT_BASE` — [flux-media-optimizer](https://github.com/stratease/flux-media-optimizer)

**Gate (all required):**

1. `WP_DEBUG` is true
2. `SCRIPT_DEBUG` is true
3. The plugin's `FLUX_*_DEV_SCRIPT_BASE` constant is defined, is a non-empty string, and points at your webpack dev server root (scheme + host + optional path)

When the gate passes, `AdminController` resolves bundle URLs as `{base}/{filename}` (PHP trims trailing slashes on the base). Otherwise WordPress always loads shipped `assets/js/dist/*.bundle.js`, even when debug flags are on.

**WordPress.org:** Plugin PHP must **not** contain hardcoded `localhost` or dev-server URLs. Keep dev URLs in local `wp-config.php` only. Add a PHPUnit source-scan test (see flux-one / flux-media-optimizer `AdminControllerScriptUrlTest`) to prevent regressions.

Example (local machine only):

```php
// wp-config.php — not shipped in the plugin
// Use the port from the Webpack dev server port registry table.
define( 'FLUX_MEDIA_OPTIMIZER_DEV_SCRIPT_BASE', 'http://localhost:3000' );
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
- [`bin/deploy-plugin.sh`](bin/deploy-plugin.sh) — commit `wporg/trunk/` to SVN and create version tags (run **after** `build-plugin.sh`).
- [`bin/fix-bin-wrappers.php`](bin/fix-bin-wrappers.php) — rewrites Composer `vendor/bin/*` shims when Strauss removes `vendor/stratease/flux-plugins-common` (required for `delete_vendor_packages`; see [Composer bin wrappers](#composer-bin-wrappers-build--deploy)).
- [`bin/plugin-dist-rsync-excludes.txt`](bin/plugin-dist-rsync-excludes.txt) — **single source of truth** for rsync `--exclude` patterns (dev dependencies, `tests/` including WorDBless `tests/wordpress/` core copy, maps, PHPUnit files, `audit-*.md`, the entire Strauss-copied `vendor-prefixed/stratease/flux-plugins-common/src/assets` tree, etc.). Edit this file when a new dev-only path must never ship.
- [`bin/verify-plugin-distribution.sh`](bin/verify-plugin-distribution.sh) — optional gate: performs the same filtered copy to a temp directory and fails if `phpunit.xml.dist`, `audit-*.md`, `*.map`, or `vendor-prefixed/stratease/flux-plugins-common/src/assets` appear under the simulated distribution tree.

### Release workflow (from a plugin root)

After your plugin’s `composer.json` includes the [Composer script setup](#composer-script-setup) below (including `fix-bin-wrappers`):

```bash
cd /path/to/your-plugin

# Installs deps, runs Strauss, copies runtime assets, fixes vendor/bin wrappers
composer install

# Build wporg/trunk (or packaging tree) — use the Composer shim, not a raw path
./vendor/bin/build-plugin.sh

# Optional: push to WordPress.org SVN (requires wporg/ checkout and credentials)
./vendor/bin/deploy-plugin.sh
```

Both `./vendor/bin/build-plugin.sh` and `./vendor/bin/deploy-plugin.sh` resolve to scripts under `vendor-prefixed/stratease/flux-plugins-common/bin/` **only after** wrappers are fixed. Without `fix-bin-wrappers`, you may see:

```text
cd: can't cd to ../stratease/flux-plugins-common/bin
exec: /build-plugin.sh: not found
```

Run the verifier from a plugin root (example):

```bash
/path/to/flux-plugins-common/bin/verify-plugin-distribution.sh /path/to/your-plugin
```

**Guideline checklist (high level, not legal advice):**

- Declare **GPL-2.0-or-later** (or compatible) for the **whole** plugin, including prefixed vendor code, in the plugin header and `readme.txt`.
- Document **external services** (API endpoints, data sent) accurately in `readme.txt` if the shipped code calls out to third-party or first-party hosted services.
- Do not ship **development-only** files (tests and WorDBless `tests/wordpress/` from Composer, source maps, audit notes, local tooling, or the Strauss-copied common asset tree under `vendor-prefixed/…/src/assets`) in the WordPress.org zip; rely on `plugin-dist-rsync-excludes.txt` and the verifier before tagging a release. Runtime common assets ship only from the host plugin’s `src/assets/common/` copy.
- User-facing strings must not imply payment unlocks **bundled** plugin code (see [docs/WPORG_COMPLIANCE.md](docs/WPORG_COMPLIANCE.md)).

### WordPress.org compliance (integrators)

Full policy: [docs/WPORG_COMPLIANCE.md](docs/WPORG_COMPLIANCE.md).

- **SaaS-only gating:** `LicenseService::is_license_valid()` may guard **outbound Flux cloud API** calls only—not local UI, settings, logs, or user-supplied API keys.
- **Language:** Prefer “optional Flux cloud processing” / “licensed cloud services”; avoid “unlock premium”, “trial”, or “pay to unlock” for code shipped in the org zip.
- **i18n:** PHP uses the host text domain via `FluxPlugins::init()` and `I18n::domain()`. JavaScript in this library must use the string literal `'flux-plugins-common'` as the second argument to `__()` (required for WP.org translation parsers—no variables or constants). Host plugins should include prefixed common JS paths when running `wp i18n make-pot`, or merge strings into their catalog per [docs/WPORG_COMPLIANCE.md](docs/WPORG_COMPLIANCE.md).
- **Script translations:** `add_filter( 'flux_suite/i18n/script_translations_path', fn () => dirname( plugin_dir_path( __FILE__ ) ) . '/languages' );`
- **Admin notices:** Invalid-license notices appear only on Flux Suite and host plugin admin screens.
- **readme.txt:** Document `https://api.fluxplugins.com`, data sent, and Terms/Privacy under External services.

## Building Assets

The shared library includes JavaScript assets that need to be built:

```bash
cd flux-plugins-common
npm install
npm run build
```

**Important:** The built bundle files (`src/assets/js/dist/*.bundle.js`) **must be committed** to this repository. Integrating plugins copy **only** `js/dist` and `images` into `src/assets/common/` (see [Asset Management](#asset-management)); they do not rebuild License/Logs/compatibility bundles locally.

## PHP tests (PHPUnit)

Library PHP behavior is covered with **PHPUnit** and [**WorDBless**](https://github.com/Automattic/wordbless) (no MySQL). From this directory:

```bash
composer install
composer test
```

Requires PHP extensions expected by PHPUnit 9 (including **mbstring**). WorDBless installs a `db.php` drop-in via Composer `post-install-cmd` / `post-update-cmd`.

Release notes for integrators: see [CHANGELOG.md](CHANGELOG.md).

For development with watch mode:

```bash
npm run dev
```

## Asset Management

### Runtime vs build-time layout

Integrating plugins use **three separate trees** for the common library:

| Location | Role | Shipped in WP.org zip? |
|----------|------|------------------------|
| `vendor/stratease/flux-plugins-common/` | Composer install + Strauss **input** (dev/CI only) | **No** (excluded by `plugin-dist-rsync-excludes.txt`) |
| `vendor-prefixed/stratease/flux-plugins-common/` | Strauss-prefixed **PHP** (`YourPlugin\FluxPlugins\Common\…`) | **Yes** (PHP only; exclude the entire `src/assets` tree plus webpack/package files via rsync excludes — runtime assets live in `src/assets/common/`) |
| `src/assets/common/` in each plugin | Runtime **static** assets (`js/dist`, `images`) passed to `FluxPlugins::init()` | **Yes** (`dist` + `images` only) |

**JavaScript source** (`src/assets/js/src/`) lives only in **this** repository (or a monorepo sibling checkout). Plugin webpack aliases `@flux-plugins-common` to that path at build time; it is not copied into each plugin.

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

Each plugin must copy **runtime** common assets (`js/dist` + `images`) from `vendor/` to `src/assets/common/` **before** Strauss runs, and must wire **`fix-bin-wrappers`** so `vendor/bin/build-plugin.sh` and `vendor/bin/deploy-plugin.sh` keep working after Strauss deletes the unprefixed package.

Add this **complete** pattern to your `composer.json` (replace `YourPlugin\\` with your Strauss namespace prefix):

```json
{
    "extra": {
        "strauss": {
            "target_directory": "vendor-prefixed",
            "namespace_prefix": "YourPlugin\\\\",
            "classmap_prefix": "YourPlugin_",
            "constant_prefix": "YourPlugin_",
            "packages": [ "stratease/flux-plugins-common" ],
            "delete_vendor_packages": true
        }
    },
    "scripts": {
        "copy-common-assets": [
            "sh -c 'SRC=vendor/stratease/flux-plugins-common/src/assets; DST=src/assets/common; if [ -d \"$SRC\" ]; then mkdir -p \"$DST/js\" \"$DST/images\"; cp -r \"$SRC/images\" \"$DST/\" 2>/dev/null || true; cp -r \"$SRC/js/dist\" \"$DST/js/\"; echo \"✅ Copied common runtime assets (js/dist + images) to src/assets/common/\"; else echo \"⚠️  Common library not in vendor/ — run composer install\"; fi'"
        ],
        "fix-bin-wrappers": [
            "sh -c 'if [ -f vendor-prefixed/stratease/flux-plugins-common/bin/fix-bin-wrappers.php ]; then php vendor-prefixed/stratease/flux-plugins-common/bin/fix-bin-wrappers.php; elif [ -f vendor/stratease/flux-plugins-common/bin/fix-bin-wrappers.php ]; then php vendor/stratease/flux-plugins-common/bin/fix-bin-wrappers.php; fi'"
        ],
        "prefix-namespaces": [
            "@copy-common-assets",
            "sh -c 'test -f ./bin/strauss.phar || curl -o bin/strauss.phar -L -C - https://github.com/BrianHenryIE/strauss/releases/latest/download/strauss.phar'",
            "@php bin/strauss.phar",
            "@composer dump-autoload",
            "@fix-bin-wrappers"
        ],
        "post-install-cmd": [ "@prefix-namespaces" ],
        "post-update-cmd": [ "@prefix-namespaces" ],
        "post-autoload-dump": [ "@fix-bin-wrappers" ]
    }
}
```

**Important:**

- `copy-common-assets` must run **before** Strauss so `vendor/stratease/flux-plugins-common` still exists.
- `delete_vendor_packages: true` removes the unprefixed common package from `vendor/` after prefixing (smaller tree; avoids duplicate autoload). Re-run `composer install` to refresh `vendor/` before another copy.
- **`fix-bin-wrappers` is required** when using `delete_vendor_packages: true` — see [Composer bin wrappers](#composer-bin-wrappers-build--deploy).
- Suite admin bundles (License, Logs, compatibility dismiss) are built **here** (`npm run build`), not in each plugin's webpack config.

**Monorepo development:** point webpack at a sibling checkout (see [Webpack Alias Configuration](#webpack-alias-configuration)) or set `FLUX_PLUGINS_COMMON_PATH` to the common library root. Optional Composer [path repository](https://getcomposer.org/doc/05-repositories.md#path-repository) for PHP.

### Composer bin wrappers (build & deploy)

This package registers Composer **bin** commands (`build-plugin.sh`, `deploy-plugin.sh`) declared in [`composer.json`](composer.json) (`"bin": [ "bin/build-plugin.sh", "bin/deploy-plugin.sh" ]`). When a plugin runs `composer install`, Composer writes shims under **`vendor/bin/`** that initially point at:

```text
vendor/stratease/flux-plugins-common/bin/
```

Strauss with **`delete_vendor_packages: true`** removes that directory after prefixing. The real scripts remain at:

```text
vendor-prefixed/stratease/flux-plugins-common/bin/build-plugin.sh
vendor-prefixed/stratease/flux-plugins-common/bin/deploy-plugin.sh
```

[`bin/fix-bin-wrappers.php`](bin/fix-bin-wrappers.php) rewrites each `vendor/bin/*.sh` shim to use `../../vendor-prefixed/stratease/flux-plugins-common/bin` and restores execute bits on the prefixed scripts.

| When | What runs | Why |
|------|-----------|-----|
| `composer install` / `update` | `@prefix-namespaces` → ends with `@fix-bin-wrappers` | Full pipeline + wrapper fix |
| `composer dump-autoload` | `post-autoload-dump` → `@fix-bin-wrappers` | Regenerated `vendor/bin` shims stay valid (CI, plugins that dump autoload without full install) |
| Manual recovery | `php vendor-prefixed/stratease/flux-plugins-common/bin/fix-bin-wrappers.php` | Same fix if wrappers were reset |

**Do not** call `build-plugin.sh` or `deploy-plugin.sh` only by path under `vendor/stratease/` — that path does not exist after Strauss. Use:

```bash
./vendor/bin/build-plugin.sh
./vendor/bin/deploy-plugin.sh
```

or, without going through Composer shims:

```bash
bash vendor-prefixed/stratease/flux-plugins-common/bin/build-plugin.sh
bash vendor-prefixed/stratease/flux-plugins-common/bin/deploy-plugin.sh
```

**Deploy:** `deploy-plugin.sh` uses the same wrapper mechanism as `build-plugin.sh`. Run `composer install` (or at least `fix-bin-wrappers`) in the plugin root before `./vendor/bin/deploy-plugin.sh` so the deploy shim targets `vendor-prefixed/…`.

Plugins with extra Strauss steps (for example webpack helper copy in **flux-ai-media-alt-creator-pro**) should still call `@fix-bin-wrappers` **after** Strauss in `prefix-namespaces` and in `post-autoload-dump`.

### Directory Structure

After `composer install` / `composer update`:

```
your-plugin/
├── src/
│   └── assets/
│       └── common/              # Runtime static assets only
│           ├── js/
│           │   └── dist/        # Pre-built bundles (from common lib)
│           └── images/
├── vendor/                      # Plugin deps (not unprefixed common after Strauss)
└── vendor-prefixed/
    └── stratease/
        └── flux-plugins-common/ # Prefixed PHP (+ dev-only asset tree)
```

`src/assets/common/` is what `MenuService` enqueues at runtime. Include `js/dist` and `images` in your plugin zip; exclude `src/assets/common/js/src` if it was ever committed, and never ship `vendor-prefixed/stratease/flux-plugins-common/src/assets` (see `plugin-dist-rsync-excludes.txt`).

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
    __( 'New Plugin', I18n::domain() ), // Short display name (drop "Flux " prefix)
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

### Plugin naming and branding

Flux plugins use consistent naming across several surfaces. Follow these rules for every new plugin.

#### WordPress plugin full name (`Plugin Name` header and `readme.txt` title)

Append `by Flux Plugins` to the official product name. Reference: [flux-media-optimizer](https://github.com/stratease/flux-media-optimizer).

**With descriptor (preferred when the product has a subtitle):**

```php
/**
 * Plugin Name: Flux Media Optimizer – Image & Video Optimization by Flux Plugins
 */
```

Use an en-dash (`–`) between the product name and descriptor.

**Without descriptor:**

```php
/**
 * Plugin Name: Flux Fixer by Flux Plugins
 */
```

Also set:

- `Author: Flux Plugins`
- `Author URI: https://fluxplugins.com`

The `readme.txt` title must match the `Plugin Name` header.

#### Flux Suite admin submenu (`MenuService::register_submenu_page` title)

Drop the `Flux ` prefix. Use the primary product name only.

| Full product name | Submenu title |
|-------------------|---------------|
| Flux Media Optimizer | Media Optimizer |
| Flux One | One |
| Flux Fixer | Fixer |

**In-app UI** (`PageLayout` titles and similar headings) is **not** covered by this rule — those may use fuller branding.

#### WordPress.org compliance

All Flux plugins must follow the [WordPress.org Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/). See also [WordPress.org packaging and bundled library](#wordpressorg-packaging-and-bundled-library) and [docs/WPORG_COMPLIANCE.md](docs/WPORG_COMPLIANCE.md).

### PHP Initialization

#### 1. Main Plugin File Setup

In your plugin's main PHP file (e.g., `your-plugin.php`), initialize the common library:

```php
<?php
/**
 * Plugin Name: Flux Example Plugin by Flux Plugins
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
            __( 'Example Plugin', 'your-plugin' ), // Submenu title — drop "Flux " prefix
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

**Monolog / `psr/log`:** This library **deprecated** then **removed** any dependency on Monolog and on `psr/log` for logging (see [Monolog and psr log deprecation and removal](#monolog-and-psr-log-deprecation-and-removal)). Call **`Logger` only**; do not depend on Monolog types from this package.

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

**Implementation notes (library 1.2.0+):**

- **Monolog and `psr/log` removed (after deprecation):** The library **no longer** declares or loads Monolog or `psr/log` for suite logging. That reliance is **deprecated** as of the v1.2.0 line and **removed** from Composer and runtime; public `Logger` method names stay the same so existing plugin call sites keep working. Full policy: [Monolog and psr log deprecation and removal](#monolog-and-psr-log-deprecation-and-removal).
- **Storage:** when site option `flux-plugins_common_options['enable_logging']` is true (default), entries are stored in `{prefix}flux_plugins_logs` via `$wpdb` (same Flux Suite → Logs UI as before). The library does **not** write ad-hoc log files under the plugin directory.
- **Host log:** levels **`error`**, **`critical`**, **`alert`**, and **`emergency`** also emit one line per event via PHP **`error_log()`** (server/host log). Context JSON in that line is truncated when large; full context remains in the database row when DB logging is enabled.
- **`Logger` does not implement `Psr\Log\LoggerInterface`.** Method names match common PSR-3 usage; avoid type-hinting `LoggerInterface` against `Logger` unless you provide an adapter.
- **Consuming plugins:** remove `monolog/monolog` and `psr/log` from your `composer.json` `require` and from Strauss `extra.strauss.packages` if they were only included for this library; run `composer update` and your prefix pipeline. Until you depend on a tagged release that ships this change, keep your previous Composer lines if you still install an older common package.
- **Local monorepo:** to test unreleased `flux-plugins-common` from a sibling directory, add a Composer **`path`** repository pointing at that clone (see [Composer path repositories](https://getcomposer.org/doc/05-repositories.md#path-repository)), then `composer update stratease/flux-plugins-common`.

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

Enqueue your React app and localize script data. Production builds always use `assets/js/dist/admin.bundle.js`. Optional webpack HMR uses a wp-config-only dev base constant (see [Optional local dev script base](#optional-local-dev-script-base-wp-config-only)); reference implementation: flux-one-command-bar `AdminController`.

```php
class AdminController {
    /**
     * @since 1.0.0
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'your-plugin-slug' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'your-plugin-admin',
            $this->get_script_url(),
            [ 'wp-api-fetch', 'wp-element', 'wp-components', 'wp-i18n' ],
            YOUR_PLUGIN_VERSION,
            true
        );

        wp_localize_script( 'your-plugin-admin', 'yourPluginAdmin', [
            'apiUrl'    => rest_url( 'your-plugin-slug/v1/' ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'adminUrl'  => admin_url(),
            'pluginUrl' => YOUR_PLUGIN_PLUGIN_URL,
        ] );

        wp_enqueue_style( 'wp-components' );
    }

    /**
     * @since 1.0.0
     * @return string
     */
    private function get_script_url() {
        $dev = $this->dev_script_url( 'admin.bundle.js' );
        if ( null !== $dev ) {
            return $dev;
        }

        return YOUR_PLUGIN_PLUGIN_URL . 'assets/js/dist/admin.bundle.js';
    }

    /**
     * @since 1.0.0
     * @param string $filename Bundle file name.
     * @return string|null
     */
    private function dev_script_url( string $filename ): ?string {
        if ( ! $this->is_dev_script_base_configured() ) {
            return null;
        }

        $base = rtrim( (string) constant( 'YOUR_PLUGIN_DEV_SCRIPT_BASE' ), '/' );
        $file = ltrim( $filename, '/' );

        return $base . '/' . $file;
    }

    /**
     * @since 1.0.0
     * @return bool
     */
    private function is_dev_script_base_configured(): bool {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) {
            return false;
        }

        if ( ! defined( 'YOUR_PLUGIN_DEV_SCRIPT_BASE' ) || ! is_string( constant( 'YOUR_PLUGIN_DEV_SCRIPT_BASE' ) ) ) {
            return false;
        }

        return '' !== trim( (string) constant( 'YOUR_PLUGIN_DEV_SCRIPT_BASE' ) );
    }
}
```

### React/JavaScript Setup

#### 1. Webpack Configuration

Create a `webpack.config.js` in your plugin that extends the common library's base config:

```javascript
const path = require('path');
const { createBaseWebpackConfig } = require('../../flux-plugins-common/webpack.config.helpers');

// Find flux-plugins-common directory (build-time only; not copied into src/assets/common/js/src)
function findFluxPluginsCommonDir() {
  const envPath = process.env.FLUX_PLUGINS_COMMON_PATH;
  const possiblePaths = [
    envPath ? path.resolve(envPath) : null,
    path.resolve(__dirname, '../../../flux-plugins-common'),
    path.resolve(__dirname, '../../flux-plugins-common'),
    path.resolve(__dirname, 'vendor-prefixed/stratease/flux-plugins-common'),
  ].filter(Boolean);

  for (const possiblePath of possiblePaths) {
    if (require('fs').existsSync(possiblePath) &&
        require('fs').existsSync(path.join(possiblePath, 'webpack.config.helpers.js'))) {
      return possiblePath;
    }
  }
  return path.resolve(__dirname, '../../../flux-plugins-common');
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

The common library provides shared license management for **optional Flux cloud services**. Do not use license checks to disable local plugin features (see [docs/WPORG_COMPLIANCE.md](docs/WPORG_COMPLIANCE.md)).

**PHP:**
```php
use YourPlugin\FluxPlugins\Common\License\LicenseService;

// Get license key
$license_key = LicenseService::get_instance()->get_license_key();

// Cloud service eligibility only (before calling api.fluxplugins.com)
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
 * Plugin Name: Flux Example Plugin by Flux Plugins
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
