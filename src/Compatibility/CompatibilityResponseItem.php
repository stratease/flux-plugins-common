<?php
/**
 * Individual compatibility response item.
 *
 * Represents a single compatibility response within the API response.
 *
 * @package FluxPlugins\Common\Compatibility
 * @since 1.0.0
 */

namespace FluxPlugins\Common\Compatibility;

/**
 * Compatibility response item class.
 *
 * @since 1.0.0
 */
class CompatibilityResponseItem {

	/**
	 * Notice type (error, warning, info, reminder).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $notice_type;

	/**
	 * Error code (machine-readable identifier).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $error_code;

	/**
	 * Human-readable message.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $message;

	/**
	 * Current API version (semver).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $current_version;

	/**
	 * Enabled flag (indicates if operations should be allowed).
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $enabled;

	/**
	 * Action information (optional).
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $action;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param array $data Response data array.
	 */
	public function __construct( array $data ) {
		$this->notice_type     = isset( $data['notice_type'] ) ? sanitize_text_field( $data['notice_type'] ) : 'info';
		$this->error_code       = isset( $data['error_code'] ) ? sanitize_text_field( $data['error_code'] ) : '';
		$this->message          = isset( $data['message'] ) ? wp_kses_post( $data['message'] ) : '';
		$this->current_version  = isset( $data['current_version'] ) ? sanitize_text_field( $data['current_version'] ) : '';
		$this->enabled          = isset( $data['enabled'] ) ? (bool) $data['enabled'] : true;

		// Action data (optional).
		if ( isset( $data['action'] ) && is_array( $data['action'] ) ) {
			$this->action = [
				'url'   => isset( $data['action']['url'] ) ? esc_url_raw( $data['action']['url'] ) : '',
				'label' => isset( $data['action']['label'] ) ? sanitize_text_field( $data['action']['label'] ) : '',
			];
		} else {
			$this->action = null;
		}
	}

	/**
	 * Get notice type.
	 *
	 * @since 1.0.0
	 * @return string Notice type (error, warning, info, reminder).
	 */
	public function get_notice_type() {
		return $this->notice_type;
	}

	/**
	 * Get error code.
	 *
	 * @since 1.0.0
	 * @return string Error code.
	 */
	public function get_error_code() {
		return $this->error_code;
	}

	/**
	 * Get message.
	 *
	 * @since 1.0.0
	 * @return string Human-readable message.
	 */
	public function get_message() {
		return $this->message;
	}

	/**
	 * Get current API version.
	 *
	 * @since 1.0.0
	 * @return string Current API version (semver).
	 */
	public function get_current_version() {
		return $this->current_version;
	}

	/**
	 * Check if operations are enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if enabled, false if operations should be blocked.
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Get action URL.
	 *
	 * @since 1.0.0
	 * @return string|null Action URL or null if not set.
	 */
	public function get_action_url() {
		return $this->action['url'] ?? null;
	}

	/**
	 * Get action label.
	 *
	 * @since 1.0.0
	 * @return string|null Action label or null if not set.
	 */
	public function get_action_label() {
		return $this->action['label'] ?? null;
	}

	/**
	 * Get action data.
	 *
	 * @since 1.0.0
	 * @return array|null Action data array or null if not set.
	 */
	public function get_action() {
		return $this->action;
	}

	/**
	 * Check if action is available.
	 *
	 * @since 1.0.0
	 * @return bool True if action is set, false otherwise.
	 */
	public function has_action() {
		return $this->action !== null && ! empty( $this->action['url'] );
	}

	/**
	 * Convert to array.
	 *
	 * @since 1.0.0
	 * @return array Array representation of the response item.
	 */
	public function to_array() {
		return [
			'notice_type'     => $this->notice_type,
			'error_code'      => $this->error_code,
			'message'         => $this->message,
			'current_version' => $this->current_version,
			'enabled'         => $this->enabled,
			'action'          => $this->action,
		];
	}
}

