# Flux Plugins Architecture Prompt Context

Last reviewed: 2026-03-15
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

## 2) Bootstrap Contract (Per Plugin)

Expected plugin bootstrap flow:

1. Load autoloaders:
   - `vendor/autoload.php`
   - `vendor-prefixed/autoload.php`
2. Call:
   - `FluxPlugins::init( $plugin_slug, $version, $text_domain, $common_assets_url )`
3. Initialize plugin app orchestrator (e.g., `App\Plugin::init()`).

This contract must remain stable across plugins.

---

## 3) Shared Library Responsibilities (`flux-plugins-common`)

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

---

## 4) Cross-Plugin State Coordination Rule

Because each plugin gets a prefixed/isolation copy, singleton state is not globally shared across plugins.

**Required pattern for cross-plugin per-request coordination:**

- WordPress hook state (`did_action()` / `do_action()`)
- Static registries only for request-scoped aggregation (e.g., settings tab collection)

Do **not** rely on inter-plugin singleton memory.

---

## 5) Assets and Build Contract

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

## 6) API / UI Architectural Pattern

### Backend
- Service-oriented design
- Controllers call facades/services (not low-level providers directly)
- Extension points via hooks/filters at service boundaries

### Frontend
- React SPA in WP admin
- Shared provider/theme from common library
- Plugin pages/routes remain plugin-owned

---

## 7) Versioning and Compatibility Expectations

- Treat `flux-plugins-common` as an internal platform dependency.
- Changes in common lib should be **backward compatible by default**.
- Breaking changes require:
  1. explicit migration note,
  2. version gate/compat check,
  3. rollout plan across consuming plugins.

---

## 8) Packaging Reliability Requirements

Release artifacts must ensure prefixed dependencies are complete and loadable.

Historical failure class to guard against:
- Missing runtime class from shared dependency in older zips (e.g., Monolog class load failure).

Minimum release validation for each plugin zip:
1. Fresh install
2. Activation
3. Admin page load
4. Core smoke flow
5. No fatal errors in logs

---

## 9) Decision Heuristics (When Designing Changes)

Prefer options that:
1. Reduce cross-plugin drift
2. Keep common interfaces stable
3. Minimize plugin-specific duplication
4. Improve packaging determinism
5. Preserve WordPress-native behavior and compatibility

Reject options that:
- introduce implicit shared runtime state across prefixed plugins,
- tightly couple common lib to one plugin’s business domain,
- require production runtime build steps.

---

## 10) Prompting Template (Copy/Paste)

Use this when asking for architecture guidance:

> We use Flux Plugins with per-plugin apps + a shared `flux-plugins-common` library, distributed via Composer + Strauss namespace prefixing into each plugin. `FluxPlugins::init(slug, version, textdomain, commonAssetsUrl)` is the bootstrap contract. Cross-plugin shared state must use WordPress hooks (`did_action`/`do_action`) rather than singleton memory. Propose a solution that is backward-compatible, packaging-safe (zip/runtime), and minimizes drift across plugins. Provide: (1) target architecture, (2) migration plan, (3) risks, (4) test matrix, (5) rollback plan.

---

## 11) Governance

- This file is the canonical baseline.
- Plugin repos may keep short `ARCHITECTURE_CONTEXT.md` files for plugin-specific deltas only.
- If conflicts exist, this file wins unless superseded by an explicit architecture decision record (ADR).
