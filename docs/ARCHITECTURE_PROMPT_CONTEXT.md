# Flux Plugins Architecture Prompt Context

Last reviewed: 2026-03-29
Owner: Flux Plugins
Status: Canonical (shared baseline)

## Purpose
Use this file as the **single source of truth** when prompting for architecture decisions, refactors, standards, or cross-plugin implementation planning across the Flux plugin suite.

---

## 1) System Model (High-Level)

Flux Plugins uses a **hybrid architecture**:

1. **Per-plugin application layer**
   - Each plugin is independently installable and ships its own PHP app/services/controllers and React admin UI.
   - Example reference plugin: `flux-ai-media-alt-creator`.

2. **Shared common library layer**
   - `flux-plugins-common` provides shared services and UI primitives (menu system, compatibility, logging, account/license infra, theme/components).

3. **Namespace isolation layer (Strauss)**
   - Each plugin prefixes shared dependencies into plugin-specific namespaces under `vendor-prefixed/`.
   - This prevents class collisions when multiple Flux plugins are active simultaneously.

---

## 2) Plugin Catalog

### Integrated Plugins (using `flux-plugins-common`)

| Plugin | Slug | Path | PHP | Dev Port | Status |
|--------|------|------|-----|----------|--------|
| Flux Media Optimizer | `flux-media-optimizer` | `wp-content/plugins/flux-media-optimizer/` | >=8.1 | 3000 | Active |
| Flux AI Alt Text & Accessibility Audit | `flux-ai-media-alt-creator` | `wp-content/plugins/flux-ai-media-alt-creator/` | >=8.0 | 3002 | Active |
| Flux AI Alt Text & Accessibility Audit Pro | `flux-ai-media-alt-creator-pro` | `wp-content/plugins/flux-ai-media-alt-creator-pro/` | >=8.0 | 3003 | Active |
| Flux AI Gutenberg Page Builder | `flux-ai-gutenberg-page-builder` | `wp-content/plugins/flux-ai-gutenberg-page-builder/` | >=8.0 | 3001 | Active |
| Flux One - Command Central | `flux-one` | `wp-content/plugins/flux-one/` | >=8.1 | 3004 | Active |
| Flux Fixer | `flux-fixer` | `wp-content/plugins/flux-fixer/` | >=8.0 | 3005 | Active |

### Non-Integrated Plugins (standalone, not using `flux-plugins-common`)

| Plugin | Slug | Path | Status | Notes |
|--------|------|------|--------|-------|
| Flux Unused Media Cleaner | `flux-unused-media-cleaner` | `wp-content/plugins/flux-unused-media-cleaner/` | Active — needs migration | Custom logger, custom license validation, menu under Media |
| Flux Accessibility Audit Scanner | `flux-accessibility-audit-scanner` | `wp-content/plugins/flux-accessibility-audit-scanner/` | Active — needs evaluation | No licensing, no logging, manual autoloading, vanilla JS, menu under Settings |

### Utility Plugins (appropriately standalone)

| Plugin | Slug | Path | Notes |
|--------|------|------|-------|
| Flux License Renewal Manager | `flux-license-renewal-manager` | `wp-content/plugins/flux-license-renewal-manager/` | Server-side WooCommerce/LMFWC utility. Single file. No admin UI. Appropriately standalone. |

### Planned Plugins (not yet built)

| Plugin | Slug |
|--------|------|
| Flux AI Featured Image Generator | `flux-ai-featured-image-generator` |

### Dev Server Port Assignments

Ports are assigned per-plugin to allow simultaneous development. When adding a new plugin, use the next available port.

| Port | Plugin |
|------|--------|
| 3000 | flux-media-optimizer |
| 3001 | flux-ai-gutenberg-page-builder |
| 3002 | flux-ai-media-alt-creator |
| 3003 | flux-ai-media-alt-creator-pro |
| 3004 | flux-one |
| 3005 | flux-fixer |

### Plugin Registry (`MenuService`)

The hard-coded plugin registry in `MenuService::init_plugin_registry()` must be updated whenever a plugin is added. The registry drives the Flux Suite landing page which shows all plugins with status badges (active, inactive, planned).

### Flux One: `config` command and suite settings catalog

Operator-facing suite settings that appear in Command Central must stay in sync end-to-end in **Flux One** (not in `flux-plugins-common`):

1. **PHP catalog** — Add or update a definition in [`SuiteConfigCatalog`](wp-content/plugins/flux-one/app/Services/SuiteConfigCatalog.php) (or merge via the `flux_one_suite_config_definitions` filter). Include `id`, `plugin_file`, `type`, `handler`, and for enums a `choices` array where applicable.
2. **REST index** — The autocomplete index is built in [`IndexController::suite_config`](wp-content/plugins/flux-one/app/Http/Controllers/IndexController.php) from active definitions; extend that payload when the client needs new fields (for example `choices` for enum keys).
3. **Command execution** — Read/write behavior must match in [`ConfigHandler`](wp-content/plugins/flux-one/app/Services/CommandHandlers/ConfigHandler.php) (`config list`, `config get`, `config set`).
4. **Client suggestions** — Keep [`suggest.ts`](wp-content/plugins/flux-one/assets/js/src/command/suggest.ts) aligned with catalog types (bools, enums, keys) so the command bar autocomplete matches what the server accepts.

Skipping any of these steps yields missing keys, wrong types in the UI, or failed `config set` validation.

---

## 3) Bootstrap Contract (Per Plugin)

Expected plugin bootstrap flow:

1. Load autoloaders:
   - `vendor/autoload.php`
   - `vendor-prefixed/autoload.php`
2. Call:
   - `FluxPlugins::init( $plugin_slug, $version, $text_domain, $common_assets_url )`
3. Initialize plugin app orchestrator (e.g., `App\Plugin::init()`).

This contract must remain stable across plugins.

### Activation Contract

On plugin activation, the plugin must:

1. Create any required database tables via `Database::create_tables()` (if applicable).
2. Initialize default settings via `Settings::initialize_defaults()`.
3. Schedule any required WP-Cron or Action Scheduler events.
4. Set a one-time activation redirect transient (60s TTL) to guide the user to the admin page.

### Deactivation Contract

On plugin deactivation, the plugin must:

1. Clear all scheduled WP-Cron events owned by the plugin.
2. Cancel all pending Action Scheduler actions in the plugin's group (if applicable).

### Uninstall Contract

On plugin uninstall (`uninstall.php` or `register_uninstall_hook`), the plugin must:

1. Drop all custom database tables.
2. Delete the plugin's main options key (e.g., `flux_{slug}_settings`).
3. Delete the plugin's DB version option (if applicable).
4. Delete all plugin-specific post meta (e.g., `DELETE FROM wp_postmeta WHERE meta_key LIKE '_flux_{slug}_%'`).
5. Clear all related transients and cron hooks.
6. Do **not** delete shared options (`flux-plugins_license_key`, `flux-plugins_account_id`, etc.) unless no other Flux plugin is active.

---

## 4) Shared Library Responsibilities (`flux-plugins-common`)

The common library is responsible for suite-level behavior:

- Flux Suite top-level admin menu
- Shared License page
- Shared Logs page
- Shared Settings host/tab registry
- Compatibility checks and notices
- Account ID service
- Shared logger infrastructure
- Shared API/client abstractions where applicable
- Shared React theme/components/assets

Common library should avoid plugin-specific business logic.

### Current PHP Services

| Service | Namespace | Pattern | Purpose |
|---------|-----------|---------|---------|
| `FluxPlugins` | `FluxPlugins\Common\` | Singleton | Bootstrap entry point, admin init, license notice |
| `MenuService` | `Services\` | Singleton | Top-level menu, submenu registration, plugin registry, license/logs/settings pages |
| `CompatibilityService` | `Services\` | Singleton | Per-plugin compatibility validation + admin notices |
| `CompatibilityValidator` | `Compatibility\` | Instance | Cached compatibility checks (4h TTL) against external API |
| `CompatibilityNoticeHandler` | `Compatibility\` | Instance | Renders WP admin notices with per-user dismissal (30 days) |
| `CompatibilityResponse` | `Compatibility\` | Value Object | Wraps compatibility API response data |
| `CompatibilityResponseItem` | `Compatibility\` | Value Object | Individual compatibility check result |
| `RestApiService` | `Services\` | Singleton | Registers shared REST routes (license, logs) once per request |
| `I18n` | `Services\` | Static | Text domain management |
| `LogsService` | `Services\` | Instance | Database query service for logs (pagination, filtering) |
| `LicenseService` | `License\` | Singleton | Shared license key management (site option), 24h auto-revalidation |
| `AccountIdService` | `Account\` | Singleton | UUID v4 account ID generation and storage (site option) |
| `ExternalApiClient` | `Api\` | Instance | HTTP client for `api.fluxplugins.com` (license, compatibility, generic CRUD) |
| `Logger` | `Logger\` | Singleton | Suite logger: wpdb log table + `error_log()` for ERROR+ (PSR-3–like methods, not `LoggerInterface`) |
| `DatabaseHandler` | `Logger\` | Instance | Persists rows to `{prefix}flux_plugins_logs` table |
| `LicenseController` | `Http\Controllers\` | Instance | REST endpoints for license CRUD under `flux-plugins-common/v1` |
| `LogsController` | `Http\Controllers\` | Instance | REST endpoint for log viewing under `flux-plugins-common/v1` |

### Current JS Components and Modules

| Module | Path | Purpose |
|--------|------|---------|
| `FluxAppProvider` | `components/FluxAppProvider/` | MUI `ThemeProvider` + `CssBaseline` + global CSS overrides for WP admin conflicts |
| `PageLayout` | `components/PageLayout/` | Standard admin page wrapper with branding (Container > Paper > BrandIcon + title) |
| `BrandIcon` | `components/PageLayout/` | Flux Plugins logo icon component |
| `LicensePage` | `components/License/` | Full license management React page (input, activate, validate, account ID) |
| `LogsPage` | `components/Logs/` | Full log viewer React page (table, filters, pagination, logging toggle) |
| `StyleShowcase` | `components/Logs/` | MUI component gallery for debugging WP admin CSS conflicts |
| `useLicense` hooks | `hooks/` | React Query hooks for license API (`useLicense`, `useActivateLicense`, `useValidateLicense`, `useAccountId`) |
| `licenseApi` | `services/` | REST client for `flux-plugins-common/v1/license` endpoints |
| `logsApi` | `services/` | REST client for `flux-plugins-common/v1/logs` endpoints |
| `theme` | `theme/` | MUI theme config (px-based typography, WP-safe palette, component overrides) |
| `license-page` entry | `admin/` | Standalone entry point that mounts `LicensePage` into `#flux-plugins-common-license-app` |
| `logs-page` entry | `admin/` | Standalone entry point that mounts `LogsPage` into `#flux-plugins-common-logs-app` |
| `compatibility-dismiss` entry | `admin/` | jQuery handler for dismissing compatibility admin notices via AJAX |
| `webpack.config.helpers` | root | `createBaseWebpackConfig()` factory for shared webpack configuration |

---

## 5) Cross-Plugin State Coordination Rule

Because each plugin gets a prefixed/isolation copy, singleton state is not globally shared across plugins.

**Required pattern for cross-plugin per-request coordination:**

- WordPress hook state (`did_action()` / `do_action()`)
- Static registries only for request-scoped aggregation (e.g., settings tab collection)

Do **not** rely on inter-plugin singleton memory.

---

## 6) Assets and Build Contract

### Common assets
- Common assets live in: `flux-plugins-common/src/assets/`
- Built artifacts are expected to be committed when required for downstream consumption.

### Plugin copy step
Each plugin must copy common assets before Strauss prefixing, typically into:
- `src/assets/common/`

Then pass that URL to `FluxPlugins::init(...)`.

### Rule
Do not require runtime build steps inside WordPress production environments.

---

## 7) Naming Conventions

### PHP

| Entity | Convention | Example |
|--------|-----------|---------|
| Plugin option key | `flux_{slug_underscored}_settings` | `flux_ai_alt_creator_settings` |
| DB version option | `flux_{slug_underscored}_db_version` | `flux_media_optimizer_db_version` |
| Post meta keys | `_flux_{slug_abbreviated}_{descriptor}` | `_flux_ai_alt_creator_scan_status` |
| WP-Cron hooks | `flux_{slug_underscored}_{action}` | `flux_media_optimizer_process_video` |
| Action Scheduler group | Plugin slug (hyphenated) | `flux-ai-media-alt-creator` |
| Constants | `FLUX_{SLUG_UPPER}_{NAME}` | `FLUX_AI_MEDIA_ALT_CREATOR_VERSION` |
| REST namespace | `{plugin-slug}/v1` | `flux-ai-media-alt-creator/v1` |
| Common REST namespace | `flux-plugins-common/v1` | — |

### Hook Naming

All hooks MUST follow the pattern: `{plugin_namespace}/{class_name}/{method_name}[/{operation}]`

- **Plugin namespace**: snake_case slug (`flux_suite` for common library, `flux_ai_alt_creator` for plugins).
- **Class name**: PascalCase class converted to snake_case (`MenuService` -> `menu_service`).
- **Method name**: Already snake_case.
- **Operation** (optional): `before`, `after`, `batch_size`, etc.

### JavaScript

| Entity | Convention | Example |
|--------|-----------|---------|
| Localized global | `window.flux{PluginCamelCase}` | `window.fluxAIMediaAltCreatorAdmin` |
| Common lib global | `window.fluxPluginsCommon` | — |
| Webpack alias (plugin) | `@flux-{plugin-slug}` | `@flux-ai-media-alt-creator` |
| Webpack alias (common) | `@flux-plugins-common` | — |
| React mount div | `#{plugin-slug}-app` | `#flux-ai-media-alt-creator-app` |

### PSR-4 Source Directory

All plugins use `app/` as the PSR-4 root for the plugin's own classes:

```json
{
  "autoload": {
    "psr-4": {
      "PluginNamespace\\App\\": "app/"
    }
  }
}
```

---

## 8) BaseController Contract

All REST controllers must extend a shared `BaseController` that provides standardized response formatting and permission checking.

### Success Response Format

```json
{
  "success": true,
  "message": "Success",
  "timestamp": "2026-03-29T12:00:00+00:00",
  "data": { }
}
```

### Error Response Format

```json
{
  "success": false,
  "message": "Human-readable error description",
  "error_code": "machine_readable_code"
}
```

### Standard Methods

| Method | Signature | Purpose |
|--------|-----------|---------|
| `create_success_response` | `($data, $message, $http_status)` | Wraps data in standard success envelope |
| `create_error_response` | `($message, $error_code, $http_status)` | Logs error via `Logger`, returns standard error envelope |
| `check_permissions` | `(WP_REST_Request $request): bool` | Default: `current_user_can('manage_options')` |

> **TODO:** Extract `BaseController` to `FluxPlugins\Common\Http\Controllers\BaseController`. Every integrated plugin currently has its own identical copy.

---

## 9) AdminController Pattern

Every plugin follows a standard admin page lifecycle. The pattern should be documented and ideally provided as an abstract base class.

### Lifecycle

1. **`init()`** — Hooks `register_menu` on WordPress `init` at priority `1` (admin only). Hooks `enqueue_admin_scripts` on `admin_enqueue_scripts`.
2. **`register_menu()`** — Calls `MenuService::register_submenu_page($slug, $title, $callback, $capability, $placement)`.
3. **`enqueue_admin_scripts($hook)`** — Only loads on the plugin's own admin page (`flux-suite_page_{slug}`). Enqueues the plugin's admin JS bundle. Localizes script data (REST URL, nonce, admin URL, plugin URL, feature flags).
4. **`get_script_url()`** — Returns `{FLUX_*_DEV_SCRIPT_BASE}/{bundle}.js` when `WP_DEBUG`, `SCRIPT_DEBUG`, and a non-empty wp-config dev base constant are all set; otherwise the production `assets/js/dist/{bundle}.js` path. Plugin PHP must not hardcode localhost URLs (WordPress.org). Reference: flux-one-command-bar `AdminController`.
5. **`render_main_page()`** — Outputs a single `<div id="{plugin-slug}-app">` as the React mount point.

### Script Localization

Each plugin localizes its admin script with at minimum:

```php
wp_localize_script($handle, $js_global_name, [
    'apiUrl'    => rest_url(),
    'nonce'     => wp_create_nonce('wp_rest'),
    'adminUrl'  => admin_url(),
    'pluginUrl' => plugin_dir_url($plugin_file),
]);
```

> **TODO:** Consider extracting an abstract `AdminController` base to the common library that handles steps 1, 3, 4, and 5 with plugin-specific values passed via constructor or abstract methods.

---

## 10) Settings / Options Contract

### Structure

Each plugin stores its settings as a single serialized array in `wp_options`:

- **Option key**: `flux_{slug_underscored}_settings` (use `_settings` suffix, not `_options`).
- **Schema-based sanitization**: Each setting should declare its type and constraints (int with min/max, bool, enum with allowed values, array with whitelist).
- **Defaults merging**: `initialize_defaults()` merges defaults with existing values via `wp_parse_args()` on activation and each retrieval.

### API Key Security

Plugins that store third-party API keys must implement the masking pattern:

1. **On read**: Replace real key with `__REDACTED__` placeholder before sending to client.
2. **On write**: If client sends `__REDACTED__`, preserve the existing stored key.
3. Never transmit real API keys to the browser.

### Settings REST Endpoints

Standard pattern for settings endpoints:

- `GET /{plugin-slug}/v1/options` — Returns all settings (API keys masked).
- `POST /{plugin-slug}/v1/options` — Updates settings with sanitization. Preserves masked API keys.

> **TODO:** Extract a `SchemaBasedSettings` base class to the common library. `flux-media-optimizer` has the most mature implementation with typed sanitization rules (int+range, bool, enum, array+whitelist) that should be the reference.

---

## 11) Extension System (Pro Add-on Architecture)

**WordPress.org free plugins:** The plugin zip must ship complete **local** functionality. Pro add-ons or Flux cloud services are distributed separately or documented as optional SaaS; do not time-limit or lock bundled code in the org build. See [WPORG_COMPLIANCE.md](WPORG_COMPLIANCE.md).

### FLUX_EXTENSIONS Registry (JavaScript)

The extension registry allows Pro add-on plugins to inject UI components into free plugin admin pages without modifying the free plugin's code.

```javascript
window.FLUX_EXTENSIONS = {
    register(slot, extension),  // Add extension to slot, sorted by priority
    get(slot, context),         // Get extensions for slot, filtered by condition
    unregister(slot, id),       // Remove extension by ID
    getSlots(),                 // List all slot names
};
```

Extensions are objects with: `{ id, slot, priority, component?, render?, label?, condition? }`.

### Standard Extension Slots

| Slot | Purpose |
|------|---------|
| `flux.admin.tabs` | Register additional admin tabs in the free plugin's SPA |
| `flux.media.table.columns` | Add custom columns to media listing tables |
| `flux.media.row.actions` | Add custom action buttons per media row |

### Global Library Exposure

For Pro add-ons to use React and shared libraries without bundling duplicates, the free plugin exposes globals at module load time:

- `window.React`
- `window.ReactDOM`
- `window.ReactJsxRuntime`
- `window.EmotionReact`
- `window.EmotionStyled`
- `window.ReactQuery`

### PHP Extension Pattern (Filter Interception)

Pro plugins intercept free plugin behavior via filters that return non-null to override:

```php
// Free plugin fires filter (returns null by default, allowing free logic to proceed):
$result = apply_filters('flux_ai_alt_creator/alt_text_api_service/generate_alt_text', null, $attachment_id, $media_url);
if ($result !== null) {
    return $result; // Pro plugin handled it
}

// Pro plugin intercepts:
add_filter('flux_ai_alt_creator/alt_text_api_service/generate_alt_text', [$this, 'intercept_generation'], 10, 3);
```

### Pro Plugin Bootstrap

Pro plugins check that the free plugin is active before initializing:

```php
if (!class_exists('FreePlugin\App\Services\SomeService')) {
    add_action('admin_notices', [$this, 'display_free_plugin_required_notice']);
    return;
}
```

> **TODO:** Extract `FLUX_EXTENSIONS` registry and global library exposure pattern to the common library JS so any plugin can support Pro add-ons without duplicating this boilerplate.

---

## 12) Database Migration Pattern

Plugins that require custom database tables should follow this pattern:

### Database Service

```php
class Database {
    public static function create_tables(): void   // Uses dbDelta() with charset_collate
    public static function drop_tables(): void      // DROP TABLE IF EXISTS
    public static function tables_exist(): bool     // SHOW TABLES LIKE check
    public static function get_db_version(): string // From wp_options
    public static function maybe_update_database(): void // Version comparison + create
}
```

### Version Tracking

- Store DB version in `flux_{slug}_db_version` option.
- Call `maybe_update_database()` on plugin load to handle upgrades.
- Use `dbDelta()` for idempotent table creation/modification.

### Table Naming

- Prefix all tables with `{wpdb->prefix}flux_{slug_abbreviated}_` (e.g., `wp_flux_media_optimizer_conversions`).

> **TODO:** Consider extracting an abstract `DatabaseMigration` base class to the common library with `create_tables()`/`drop_tables()`/`maybe_update_database()` boilerplate.

---

## 13) Action Scheduler Integration

Plugins that need background processing should use Action Scheduler (bundled via Composer as `woocommerce/action-scheduler`).

### Loader Pattern

```php
class ActionSchedulerService {
    public function init(): void {
        if (function_exists('as_schedule_single_action')) {
            return; // Already loaded (e.g., by WooCommerce)
        }
        require_once PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
    }
}
```

### Conventions

- **Group name**: Plugin slug (hyphenated), e.g., `flux-ai-media-alt-creator`.
- **Hook naming**: `{plugin_slug}/{service}/{action}`, e.g., `flux_ai_alt_creator/async_job_service/generate_alt_text_batch`.
- **Exclude from Strauss**: Action Scheduler uses global functions and must not be namespace-prefixed.

> **TODO:** Extract the Action Scheduler loader guard to the common library so plugins don't duplicate the "check if loaded, load if not" pattern.

---

## 14) UsageTracker Pattern

Plugins that call external APIs with per-request costs should track usage with the shared `UsageTracker` pattern.

### Data Model

Stored as a single WP option (`flux_{slug}_usage_current_month`):

```php
[
    'requests_count'  => int,
    'tokens_used'     => int,
    'cost_estimate'   => float,
    'last_reset_date' => 'Y-m-01',
    'requests'        => [ /* last 1000 request records */ ],
]
```

### Auto-Reset

The tracker compares `last_reset_date` with the current month's first day. If different, all counters reset automatically.

### Methods

- `track_request($tokens_used, $model, $cost)` — Increments counters, appends record.
- `get_current_month_usage()` — Returns usage data (auto-resets if needed).
- `reset_monthly()` — Forces reset and fires `{plugin_slug}_reset_usage` action.

> **TODO:** Extract `UsageTracker` to `FluxPlugins\Common\Services\UsageTracker`. Both `flux-ai-media-alt-creator` and `flux-ai-gutenberg-page-builder` have near-identical implementations.

---

## 15) Frontend Patterns

### JS API Service

Every plugin creates a singleton `ApiService` class wrapping `@wordpress/api-fetch`:

```javascript
class ApiService {
    constructor() {
        this.namespace = '{plugin-slug}/v1';
        // Configure createRootURLMiddleware from localized apiUrl
    }

    async request(endpoint, options = {}) {
        // Prepends namespace, adds nonce header
        // Unwraps { success, data } responses
    }

    // Plugin-specific methods: getMedia(), getOptions(), updateOptions(), etc.
}

export const apiService = new ApiService();
```

### React App Shell

Standard SPA structure used by all integrated plugins:

```
ErrorBoundary > QueryClientProvider > FluxAppProvider > HashRouter > PageLayout
```

- `QueryClient` config: retry 1-2, no refetch on window focus.
- `HashRouter` for hash-based routing (e.g., `#/overview`, `#/settings`).
- `FluxAppProvider` from common library wraps MUI `ThemeProvider`.
- `PageLayout` from common library provides branded container.

### React Query Conventions

| Setting | Value | Notes |
|---------|-------|-------|
| `staleTime` for settings | 5 minutes | Settings change infrequently |
| `staleTime` for live data | 30 seconds | Media lists, conversion stats |
| `staleTime` for static data | `Infinity` | Account ID, field visibility |
| Mutations | Invalidate related queries on success | Use `queryClient.invalidateQueries()` |

### ErrorBoundary

Standard React class component that catches errors, logs to console in development, and shows an MUI `Alert` with a "Try Again" button.

> **TODO:** Extract `ErrorBoundary` to common library JS. Both `flux-media-optimizer` and `flux-ai-media-alt-creator` have their own copies.

> **TODO:** Extract a base `ApiService` class or factory to common library JS to reduce boilerplate across plugins.

---

## 16) API / UI Architectural Pattern

### Backend
- Service-oriented design.
- Controllers extend `BaseController` and call facades/services (not low-level providers directly).
- Extension points via hooks/filters at service boundaries.
- All REST endpoints require `manage_options` capability unless explicitly public (e.g., accessibility audit scanner).

### Frontend
- React SPA in WP admin.
- Shared provider/theme from common library.
- Plugin pages/routes remain plugin-owned.
- All typography uses `px` values (not `rem`/`em`) to avoid WP admin sizing conflicts.

---

## 17) Testing Conventions

### PHP

| Tool | Purpose | Config |
|------|---------|--------|
| PHPUnit 9.x | Unit tests | `composer test` |
| PHPStan | Static analysis | `composer phpstan` |
| PHPCS + WPCS | WordPress coding standards | `composer phpcs` |
| Combined | All quality checks | `composer quality` (phpcs + phpstan + test) |

### JavaScript

| Tool | Purpose | Config |
|------|---------|--------|
| ESLint | Linting | `npm run lint` |
| Playwright | E2E tests | `tests/regression/` |

### Release Validation

Minimum release validation for each plugin zip:
1. Fresh install
2. Activation
3. Admin page load
4. Core smoke flow
5. No fatal errors in logs

---

## 18) WP-CLI Integration

Plugins that provide WP-CLI commands should:

1. Register commands under the `flux-{slug-abbreviated}` namespace (e.g., `wp flux-media-optimizer convert-all`).
2. Extend `WP_CLI_Command`.
3. Create the full service graph in the constructor (CLI context has no admin hooks).
4. Use `WP_CLI::success()`, `WP_CLI::error()`, `WP_CLI::log()` for output.
5. Guard destructive operations with `WP_CLI::confirm()`.

---

## 19) Versioning and Compatibility Expectations

- Treat `flux-plugins-common` as an internal platform dependency.
- Changes in common lib should be **backward compatible by default**.
- Breaking changes require:
  1. explicit migration note,
  2. version gate/compat check,
  3. rollout plan across consuming plugins.

---

## 20) Packaging Reliability Requirements

Release artifacts must ensure prefixed dependencies are complete and loadable.

Historical failure class to guard against:
- Missing runtime class from shared dependency in older zips (e.g., missing prefixed class after dependency removal).

### Strauss Configuration

Each plugin's `composer.json` must declare Strauss config in `extra.strauss`:

- **Target**: `vendor-prefixed/`
- **Namespace prefix**: `{PluginNamespace}\` (e.g., `FluxMedia\`, `FluxAIMediaAltCreator\`)
- **Packages to prefix**: `stratease/flux-plugins-common` only (Monolog/psr-log removed; do not list obsolete packages).
- **Packages to exclude**: `woocommerce/action-scheduler` (uses global functions)
- **Post-install/update**: Auto-runs `prefix-namespaces` composer script

### Composer Scripts Contract

Each plugin must provide these composer scripts (canonical JSON in [`README.md`](../README.md#composer-script-setup)):

- `copy-common-assets` — Copies common runtime assets (`js/dist`, `images`) to `src/assets/common/` before Strauss runs (not `js/src`).
- `fix-bin-wrappers` — Runs [`bin/fix-bin-wrappers.php`](../bin/fix-bin-wrappers.php) so `vendor/bin/build-plugin.sh` and `vendor/bin/deploy-plugin.sh` point at `vendor-prefixed/stratease/flux-plugins-common/bin/` after `delete_vendor_packages` removes `vendor/stratease/…`.
- `delete_vendor_packages` (Strauss) — Removes unprefixed `stratease/flux-plugins-common` from `vendor/` after prefixing.
- `prefix-namespaces` — Runs copy, downloads Strauss if needed, runs Strauss, dumps autoload, **`@fix-bin-wrappers`** (last).
- `post-install-cmd` / `post-update-cmd` — Auto-runs `prefix-namespaces`.
- `post-autoload-dump` — **`@fix-bin-wrappers`** so regenerated Composer bin shims stay valid before `./vendor/bin/build-plugin.sh` or `./vendor/bin/deploy-plugin.sh`.

**Release commands (plugin root):** `composer install` → `./vendor/bin/build-plugin.sh` → `./vendor/bin/deploy-plugin.sh` (optional).

---

## 21) Decision Heuristics (When Designing Changes)

Prefer options that:
1. Reduce cross-plugin drift
2. Keep common interfaces stable
3. Minimize plugin-specific duplication
4. Improve packaging determinism
5. Preserve WordPress-native behavior and compatibility

Reject options that:
- introduce implicit shared runtime state across prefixed plugins,
- tightly couple common lib to one plugin's business domain,
- require production runtime build steps.

---

## 22) Prompting Template (Copy/Paste)

Use this when asking for architecture guidance:

> We use Flux Plugins with per-plugin apps + a shared `flux-plugins-common` library, distributed via Composer + Strauss namespace prefixing into each plugin. `FluxPlugins::init(slug, version, textdomain, commonAssetsUrl)` is the bootstrap contract. Cross-plugin shared state must use WordPress hooks (`did_action`/`do_action`) rather than singleton memory. Propose a solution that is backward-compatible, packaging-safe (zip/runtime), and minimizes drift across plugins. Provide: (1) target architecture, (2) migration plan, (3) risks, (4) test matrix, (5) rollback plan.

---

## 23) Governance

- This file is the canonical baseline.
- Plugin repos may keep short `ARCHITECTURE_CONTEXT.md` files for plugin-specific deltas only.
- If conflicts exist, this file wins unless superseded by an explicit architecture decision record (ADR).

---

## 24) TODO: Per-Plugin Action Items

The following items were identified during the 2026-03-29 cross-plugin architecture review. They are organized by target (common library first, then each plugin).

---

### `flux-plugins-common` (`flux-plugins-common/`)

#### Phase 1 — High Impact Extractions

- [ ] **Extract `BaseController`** to `FluxPlugins\Common\Http\Controllers\BaseController`. All four integrated plugins have identical copies providing `create_success_response()`, `create_error_response()`, and `check_permissions()`. This is the single most duplicated pattern.
- [ ] **Extract `ErrorBoundary`** React component to common JS (`src/assets/js/src/components/common/ErrorBoundary.js`). Both `flux-media-optimizer` and `flux-ai-media-alt-creator` have their own copies.
- [ ] **Update plugin registry** in `MenuService::init_plugin_registry()`. Currently hard-codes only 5 entries. Missing: `flux-ai-gutenberg-page-builder` (exists and integrated), `flux-accessibility-audit-scanner` (exists). `flux-unused-media-cleaner` is listed as "planned" but exists and is active.

#### Phase 2 — Medium Impact Extractions

- [ ] **Extract `UsageTracker`** to `FluxPlugins\Common\Services\UsageTracker`. Both `flux-ai-media-alt-creator` and `flux-ai-gutenberg-page-builder` have near-identical implementations with monthly auto-reset, request counting, and cost estimation.
- [ ] **Extract `FLUX_EXTENSIONS` registry** and global React/library exposure pattern to common JS. Currently defined inline in `flux-ai-media-alt-creator`'s entry point. Any plugin that needs a Pro add-on will need this.
- [ ] **Extract Action Scheduler loader** to `FluxPlugins\Common\Services\ActionSchedulerLoader`. Both `flux-media-optimizer` and `flux-ai-media-alt-creator` have their own "check if loaded, load if not" guard.
- [ ] **Extract abstract `AdminController`** base class to common library. All plugins follow identical init/menu/enqueue/render lifecycle. The base class should handle steps 1, 3-5 with plugin-specific values via constructor or abstract methods.

#### Phase 3 — Convention Enforcement

- [ ] **Extract `SchemaBasedSettings`** base class. `flux-media-optimizer` has the most mature schema-based sanitization (int+range, bool, enum, array+whitelist). Other plugins would benefit from this pattern.
- [ ] **Extract base JS `ApiService`** class or factory to reduce boilerplate across plugins. Every plugin has an identical `request()` method wrapping `@wordpress/api-fetch`.
- [ ] **Consider extracting `DatabaseMigration`** abstract base if a third plugin needs custom tables.

---

### `flux-media-optimizer` (`wp-content/plugins/flux-media-optimizer/`)

- [ ] **Fix bug in `ExternalOptimizationProvider::retry_failed_job()`**. References undefined `$job` variable (lines ~162/184 accessing `$job['formats']` and `$job['sizes']`). This will cause a PHP error when retrying failed external jobs.
- [ ] **Remove duplicate `LogsService`/`LogsController`**. The plugin has its own logs endpoint (`flux-media-optimizer/v1/logs`) AND the common library registers another set (`flux-plugins-common/v1/logs`). Evaluate whether the plugin-specific one can be removed, or if the common one needs the same filtering capabilities.
- [ ] **Refactor `WordPressProvider`** (~1600 lines). This class handles upload interception, URL rewriting, HTML rendering, admin fields, AJAX handlers, and deletion. Consider splitting into focused classes (e.g., `UploadHandler`, `UrlRewriteHandler`, `AdminFieldsHandler`).
- [ ] **Implement webhook signature verification**. `WebhookController::verify_webhook()` currently always returns `true`. While `account_id` validation provides some security, signature-based verification would be stronger.
- [ ] **Migrate to shared `BaseController`** once extracted to common library. Remove local copy.
- [ ] **Rename option key** from `flux_media_optimizer_options` to `flux_media_optimizer_settings` to match the convention. Requires a one-time migration on upgrade.

---

### `flux-ai-media-alt-creator` (`wp-content/plugins/flux-ai-media-alt-creator/`)

- [ ] **Remove dead `NoOpVisionProvider`** (`app/Services/NoOpVisionProvider.php`). The `VisionProviderFactory` uses `NoConfigVisionProvider` from the `Vision/` directory instead. Two classes exist with overlapping purpose; only `NoConfigVisionProvider` is used.
- [ ] **Migrate to shared `BaseController`** once extracted to common library. Remove local copy.
- [ ] **Migrate to shared `UsageTracker`** once extracted to common library. Remove local copy.
- [ ] **Migrate `FLUX_EXTENSIONS` registry** to common library JS once extracted. Update `admin/index.js` to import from `@flux-plugins-common` instead of defining inline.
- [ ] **Consider extracting AI API client abstraction**. `OpenAIApiClient`, `GeminiApiClient`, and `ClaudeApiClient` share ~70% identical code (`make_request()`, `extract_error_message()`, response parsing). An abstract `AbstractVisionApiClient` within the plugin would reduce internal duplication.

---

### `flux-ai-media-alt-creator-pro` (`wp-content/plugins/flux-ai-media-alt-creator-pro/`)

- [ ] **Migrate to shared `BaseController`** once extracted to common library. Remove local copy.

---

### `flux-ai-gutenberg-page-builder` (`wp-content/plugins/flux-ai-gutenberg-page-builder/`)

- [ ] **Migrate to shared `UsageTracker`** once extracted to common library. Remove local copy.
- [ ] **Add to plugin registry** in `MenuService::init_plugin_registry()`. This plugin is fully integrated but missing from the Flux Suite landing page.

---

### `flux-unused-media-cleaner` (`wp-content/plugins/flux-unused-media-cleaner/`)

This plugin needs a full migration to the common library. It currently operates entirely standalone.

- [ ] **Add `stratease/flux-plugins-common` as a Composer dependency** and configure Strauss namespace prefixing.
- [ ] **Replace custom `Logger`** with common library `Logger`. The current logger has an empty `setup_handlers()` — no handler is attached, so logs go nowhere.
- [ ] **Replace custom license validation** with common library `LicenseService`. The current implementation uses a 30-day validation cache (vs the common library's 24-hour cache), which is a security concern.
- [ ] **Replace custom account ID generation** (`flux_unused_media_cleaner_ensure_account_id`) with common library `AccountIdService`.
- [ ] **Move admin menu** from `Media > Flux Unused Media Cleaner` to `Flux Suite > Unused Media Cleaner` via `MenuService::register_submenu_page()`.
- [ ] **Add compatibility checks** via `CompatibilityService`.
- [ ] **Adopt common webpack config** using `createBaseWebpackConfig()` from common library.
- [ ] **Adopt `FluxAppProvider`** and `PageLayout` from common library in the React app.
- [ ] **Register logs page** via `MenuService::register_logs_page()`.
- [ ] **Register license page** via `MenuService::register_license_page()`.
- [ ] **Follow bootstrap contract**: Call `FluxPlugins::init()` in the main plugin file.

---

### `flux-accessibility-audit-scanner` (`wp-content/plugins/flux-accessibility-audit-scanner/`)

This plugin is architecturally the most different. Evaluate whether full integration is appropriate given its nature as a free public-facing tool.

- [ ] **Evaluate common library integration**. This plugin has no licensing, no logging, no compatibility checks, uses vanilla JS (no React), and a PHP-rendered admin page. If it remains free with no Pro tier, full integration may not be warranted.
- [ ] **If integrating**: Add Composer autoloading (PSR-4), replace manual `require_once` with autoloader, add `flux-plugins-common`, follow bootstrap contract.
- [ ] **Move admin menu** from `Settings > Flux Audit Scanner` to `Flux Suite > Accessibility Audit` via `MenuService::register_submenu_page()` (if integrating).
- [ ] **Add to plugin registry** in `MenuService::init_plugin_registry()` regardless of integration status (so it appears on the Flux Suite landing page).
- [ ] **Add logging** via common library `Logger` for crawl/scan operations (if integrating).

---

### `flux-license-renewal-manager` (`wp-content/plugins/flux-license-renewal-manager/`)

No action items. This is a server-side WooCommerce/LMFWC utility that is appropriately standalone. It has no admin UI, no user-facing features, and no need for the common library.
