# WordPress.org compliance (Flux Plugins Common)

Single source of truth for integrators shipping this library in [WordPress.org](https://wordpress.org/plugins/) plugins. Not legal advice.

Reference: [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).

## SaaS-only gating policy

A valid Flux Suite license may gate **only optional cloud processing** via `https://api.fluxplugins.com` (and documented related endpoints).

**Must remain available without a license:**

- All admin UI, settings, and logs shipped in the plugin zip
- Features that run locally in PHP or in the browser
- User-supplied API keys (BYOK) for third-party AI or media APIs

**May require a valid license:**

- Outbound requests to Flux-hosted processing APIs
- Hosted quotas, automation, or CDN pipelines documented as cloud services

Do **not** call `LicenseService::is_license_valid()` to disable local code paths, hide settings tabs, or block BYOK flows.

## User-facing language

| Avoid | Prefer |
|-------|--------|
| unlock premium features | optional Flux cloud processing |
| Upgrade to Pro (on org builds) | Flux Suite cloud license / View plans |
| Premium features are enabled | Licensed cloud services are active |
| trial, locked, pay to unlock | cloud license, optional service |
| Single license unlocks all premium features | One license for optional cloud processing across Suite plugins |

See updated strings in `LicensePage.js`, `UpsellCard.js`, `FluxPlugins.php`, and `MenuService.php`.

## External services (`readme.txt`)

Each host plugin must document under **External services**:

- Service URL: `https://api.fluxplugins.com`
- Purpose: hosted processing (not license-only); license validation is part of that service
- Data sent: license key (when provided), account UUID, site URL, plugin version, and any media/metadata required for the feature
- Links to Terms of Use and Privacy Policy

## i18n

- **PHP:** use `I18n::domain()` (set in `FluxPlugins::init()` with the **host** plugin text domain).
- **JavaScript (this library):** every `__()`, `_x()`, etc. must use a **string literal** text domain: `'flux-plugins-common'`. Do **not** pass variables, constants, or runtime values as the text-domain argument—WordPress.org tooling and `wp i18n make-pot` require literals.
- **Host plugins:** run `wp i18n make-pot` with paths that include prefixed `vendor-prefixed/.../flux-plugins-common/src/assets/js/src` (and PHP under the same tree), or maintain a separate catalog for common strings. Align shipped translations with the text domain declared in each file’s `__()` calls.
- **Script translations:** optional `add_filter( 'flux_suite/i18n/script_translations_path', fn() => dirname( plugin_dir_path( __FILE__ ) ) . '/languages' );` for `wp_set_script_translations()` on license/logs bundles (uses host domain in PHP only).

## Admin notices

Invalid-license notices are limited to Flux Suite and host plugin admin screens (see `FluxPlugins::should_show_license_notice_on_screen()`). They must state that **local features are not disabled**.

## Integrator checklist (host plugins)

Complete in each WordPress.org plugin repository (separate PRs):

### flux-media-optimizer

- [ ] Confirm local AVIF/WebP processing is not gated on `is_license_valid()`
- [ ] `readme.txt` external services block for `api.fluxplugins.com`
- [ ] `add_filter( 'flux_suite/i18n/script_translations_path', ... )` if using script translations

### flux-ai-media-alt-creator

- [ ] BYOK (OpenAI/Gemini/Claude) paths never blocked by `SuiteLicenseHelper` / `is_license_valid()`
- [ ] Quota messaging applies only to hosted suite API, not BYOK
- [ ] `readme.txt` external services block

### flux-one

- [ ] Audit any `is_license_valid()` usage for cloud-only gating
- [ ] `readme.txt` external services block

### All org plugins

- [ ] No user-facing copy implying bundled code is locked until payment
- [ ] Consider omitting Pro SKUs from marketing registry on org builds via `flux_suite/menu_service/plugin_registry` filter (if added)

## GPL and packaging

- Whole plugin (including `vendor-prefixed/`) must be GPL-2.0-or-later in header and `readme.txt`
- Use `bin/plugin-dist-rsync-excludes.txt` before release; run `bin/verify-plugin-distribution.sh`
