<?php

/**
 * Constants for Flux Plugins Common library.
 *
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * Constants are defined in the global namespace (outside any namespace declaration)
 * so they can be accessed from anywhere in WordPress.
 *
 * ## Constant Naming Convention
 *
 * All constants for the Flux Plugins Common library follow this pattern:
 * `FLUX_PLUGINS_COMMON_{FEATURE}_{OPTION}`
 *
 * - **Prefix**: `FLUX_PLUGINS_COMMON_` - Identifies constants belonging to the shared library
 * - **Feature**: The feature or service the constant affects (e.g., `EXTERNAL_SERVICE`, `DISABLE_CACHE`)
 * - **Option**: The specific option or setting (e.g., `URL`, `TIMEOUT`)
 *
 * Examples:
 * - `FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL` - Base URL for the external API service
 * - `FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT` - Request timeout in seconds
 * - `FLUX_PLUGINS_COMMON_DISABLE_CACHE` - Disable caching for compatibility checks
 *
 * All constants can be overridden in `wp-config.php` by defining them before the library loads.
 *
 * @package \FluxPlugins\Common
 * @since 1.0.0
 * @since 1.0.0 Added externally managed source notice.
 */
// Constants must be defined in global namespace.
if (!defined('FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL')) {
    /**
     * External service base URL.
     *
     * Can be overridden in wp-config.php or by plugins.
     *
     * @since 1.0.0
     */
    define('FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL', 'https://api.fluxplugins.com');
}
if (!defined('FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT')) {
    /**
     * External service request timeout in seconds.
     *
     * Can be overridden in wp-config.php or by plugins.
     *
     * @since 1.0.0
     */
    define('FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT', 15);
}
if (!defined('FLUX_PLUGINS_COMMON_DISABLE_CACHE')) {
    /**
     * Disable caching for compatibility checks.
     *
     * When set to true, compatibility check results will not be cached,
     * forcing fresh API requests on every check. Useful for development and debugging.
     *
     * Can be overridden in wp-config.php or by plugins.
     *
     * @since 1.0.0
     */
    define('FLUX_PLUGINS_COMMON_DISABLE_CACHE', false);
}