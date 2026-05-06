<?php
/**
 * Admin notice handler for compatibility validation.
 *
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * Displays WordPress admin notices based on compatibility validation results.
 *
 * @package FluxPlugins\Common\Compatibility
 * @since 1.0.0
 * @since 1.0.0 Added externally managed source notice.
 */

namespace FluxPlugins\Common\Compatibility;

use FluxPlugins\Common\Services\I18n;

/**
 * Compatibility notice handler class.
 *
 * @since 1.0.0
 */
class CompatibilityNoticeHandler {

	/**
	 * Compatibility validator instance.
	 *
	 * @since 1.0.0
	 * @var CompatibilityValidator
	 */
	private $validator;

	/**
	 * Dismissal transient name prefix.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $dismissal_transient_prefix;

	/**
	 * Plugin version for cache busting.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_version;

	/**
	 * AJAX action name for dismissing notices.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $ajax_action;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param CompatibilityValidator $validator Compatibility validator instance.
	 * @param string                 $plugin_version Plugin version for cache busting.
	 * @param string                 $dismissal_transient_prefix Optional dismissal transient prefix (default: 'flux_plugins_compatibility_dismissed_').
	 * @param string                 $ajax_action Optional AJAX action name (default: 'flux_plugins_dismiss_compatibility_notice').
	 */
	public function __construct( CompatibilityValidator $validator, $plugin_version, $dismissal_transient_prefix = 'flux_plugins_compatibility_dismissed_', $ajax_action = 'flux_plugins_dismiss_compatibility_notice' ) {
		$this->validator                  = $validator;
		$this->plugin_version             = $plugin_version;
		$this->dismissal_transient_prefix = $dismissal_transient_prefix;
		$this->ajax_action                = $ajax_action;
	}

	/**
	 * Initialize notice handling.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		// Only show notices in admin area.
		if ( ! is_admin() ) {
			return;
		}

		// Register admin notice hook.
		add_action( 'admin_notices', [ $this, 'display_notice' ] );

		// Register AJAX handler for dismissing notices (plugin-specific action).
		add_action( 'wp_ajax_' . $this->ajax_action, [ $this, 'handle_dismiss_notice' ] );

		// Note: Script enqueuing is handled by CompatibilityService to ensure
		// assets are only enqueued once across all plugins.
	}

	/**
	 * Display compatibility notices if needed.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_notice() {
		// Only show to users with manage_options capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notices = $this->validator->get_notices();

		if ( empty( $notices ) ) {
			return;
		}

		// Display each notice.
		foreach ( $notices as $notice ) {
			// Generate unique hash from error_code and message.
			$error_code  = isset( $notice['error_code'] ) ? $notice['error_code'] : $notice['code'];
			$notice_hash = $this->generate_notice_hash( $error_code, $notice['message'] );

			// Check if notice has been dismissed.
			$dismissed = $this->is_notice_dismissed( $notice_hash );
			if ( $dismissed ) {
				continue;
			}

			// Determine notice class based on type.
			$notice_class = $this->get_notice_class( $notice['type'] );

			// Build notice HTML.
			$message = $notice['message'];

			// Add action button/link if provided.
			$action_html = '';
			if ( $notice['action'] && ! empty( $notice['action']['url'] ) && ! empty( $notice['action']['label'] ) ) {
				$action_html = sprintf(
					' <a href="%s" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="margin-left: 10px;">%s</a>',
					esc_url( $notice['action']['url'] ),
					esc_html( $notice['action']['label'] )
				);
			}

			// Add dismiss button for notices.
			// Nonce name is plugin-specific via ajax_action to avoid collisions.
			$nonce_name = 'flux_plugins_dismiss_compatibility_' . $notice_hash;
			$dismiss_url = wp_nonce_url(
				admin_url( 'admin-ajax.php?action=' . urlencode( $this->ajax_action ) . '&hash=' . urlencode( $notice_hash ) ),
				$nonce_name
			);
			$dismiss_html = sprintf(
				'<button type="button" class="notice-dismiss flux-plugins-dismiss" data-dismiss-url="%s" data-hash="%s"><span class="screen-reader-text">%s</span></button>',
				esc_url( $dismiss_url ),
				esc_attr( $notice_hash ),
				esc_html__( 'Dismiss this notice', I18n::domain() )
			);

			// Output notice.
			printf(
				'<div class="notice %s is-dismissible flux-plugins-compatibility-notice" data-hash="%s">%s<p>%s%s</p></div>',
				esc_attr( $notice_class ),
				esc_attr( $notice_hash ),
				$dismiss_html,
				wp_kses_post( $message ),
				$action_html
			);
		}
	}

	/**
	 * Get WordPress notice class based on notice type.
	 *
	 * @since 1.0.0
	 * @param string $type Notice type (error, warning, info, reminder).
	 * @return string WordPress notice class.
	 */
	private function get_notice_class( $type ) {
		switch ( $type ) {
			case 'error':
				return 'notice-error';
			case 'warning':
				return 'notice-warning';
			case 'info':
			case 'reminder':
			default:
				return 'notice-info';
		}
	}


	/**
	 * Handle AJAX request to dismiss notice.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_dismiss_notice() {
		// Verify nonce and get hash.
		$hash = isset( $_GET['hash'] ) ? sanitize_text_field( $_GET['hash'] ) : '';
		if ( empty( $hash ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid notice hash', I18n::domain() ) ] );
		}

		// Nonce name is plugin-specific via ajax_action to avoid collisions.
		check_ajax_referer( 'flux_plugins_dismiss_compatibility_' . $hash, '_wpnonce' );

		// Verify user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions', I18n::domain() ) ] );
		}

		// Dismiss notice.
		$this->dismiss_notice( $hash );

		wp_send_json_success( [ 'message' => __( 'Notice dismissed', I18n::domain() ) ] );
	}

	/**
	 * Generate a unique hash for a notice based on error_code and message.
	 *
	 * @since 1.0.0
	 * @param string $error_code Error code.
	 * @param string $message    Message.
	 * @return string Hashed notice identifier.
	 */
	private function generate_notice_hash( $error_code, $message ) {
		$combined = $error_code . '|' . $message;
		return md5( $combined );
	}

	/**
	 * Check if a notice has been dismissed.
	 *
	 * @since 1.0.0
	 * @param string $hash Notice hash.
	 * @return bool True if dismissed, false otherwise.
	 */
	private function is_notice_dismissed( $hash ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$transient_name = $this->dismissal_transient_prefix . $user_id . '_' . $hash;
		return (bool) get_transient( $transient_name );
	}

	/**
	 * Dismiss a notice.
	 *
	 * @since 1.0.0
	 * @param string $hash Notice hash.
	 * @return void
	 */
	private function dismiss_notice( $hash ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$transient_name = $this->dismissal_transient_prefix . $user_id . '_' . $hash;
		// Store for 30 days (2592000 seconds).
		set_transient( $transient_name, true, 30 * DAY_IN_SECONDS );
	}
}

