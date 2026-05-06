<?php
/**
 * Internationalization service for Flux Plugins suite.
 *
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * @package FluxPlugins\Common\Services
 * @since 1.0.0
 * @since 1.0.0 Added externally managed source notice.
 */

namespace FluxPlugins\Common\Services;

/**
 * Internationalization service.
 *
 * Stores and retrieves the text domain for the current plugin instance.
 * Each plugin instance is namespaced via Strauss, so this service handles one plugin.
 *
 * @since 1.0.0
 */
class I18n {

	/**
	 * Text domain for translations.
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	private static $text_domain = null;

	/**
	 * Set the text domain for this plugin instance.
	 *
	 * @since 1.0.0
	 * @param string $text_domain Text domain (e.g., 'flux-media-optimizer').
	 * @return void
	 */
	public static function set_domain( $text_domain ) {
		self::$text_domain = $text_domain;
	}

	/**
	 * Get the text domain for this plugin instance.
	 *
	 * @since 1.0.0
	 * @return string Text domain, or empty string if not set.
	 */
	public static function domain() {
		return self::$text_domain !== null ? self::$text_domain : '';
	}
}

