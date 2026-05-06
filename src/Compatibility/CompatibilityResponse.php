<?php
/**
 * Compatibility API response object.
 *
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * Represents the structured response from the compatibility check API endpoint.
 *
 * @package FluxPlugins\Common\Compatibility
 * @since 1.0.0
 * @since 1.0.0 Added externally managed source notice.
 */

namespace FluxPlugins\Common\Compatibility;

/**
 * Compatibility response class.
 *
 * @since 1.0.0
 */
class CompatibilityResponse {

	/**
	 * Success status.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $success;

	/**
	 * Array of compatibility response items.
	 *
	 * @since 1.0.0
	 * @var CompatibilityResponseItem[]
	 */
	private $compatibility_responses;

	/**
	 * Cache TTL in seconds from API response.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $cache_ttl_seconds;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param array $data Response data array from API.
	 */
	public function __construct( array $data ) {
		$this->success = isset( $data['success'] ) ? (bool) $data['success'] : false;
		// If cache is disabled via constant, set TTL to 0, otherwise use API-provided TTL.
		if ( defined( 'FLUX_PLUGINS_COMMON_DISABLE_CACHE' ) && FLUX_PLUGINS_COMMON_DISABLE_CACHE ) {
			$this->cache_ttl_seconds = 0;
		} else {
			$this->cache_ttl_seconds = isset( $data['cache_ttl_seconds'] ) ? absint( $data['cache_ttl_seconds'] ) : 0;
		}

		// Parse compatibility responses.
		$this->compatibility_responses = [];
		if ( isset( $data['compatibility_responses'] ) && is_array( $data['compatibility_responses'] ) ) {
			foreach ( $data['compatibility_responses'] as $response_data ) {
				if ( is_array( $response_data ) ) {
					$this->compatibility_responses[] = new CompatibilityResponseItem( $response_data );
				}
			}
		}
	}

	/**
	 * Check if the response indicates success.
	 *
	 * @since 1.0.0
	 * @return bool True if successful, false otherwise.
	 */
	public function is_success() {
		return $this->success;
	}

	/**
	 * Get all compatibility response items.
	 *
	 * @since 1.0.0
	 * @return CompatibilityResponseItem[] Array of compatibility response items.
	 */
	public function get_compatibility_responses() {
		return $this->compatibility_responses;
	}

	/**
	 * Get the first compatibility response item.
	 *
	 * @since 1.0.0
	 * @return CompatibilityResponseItem|null First response item or null if none.
	 */
	public function get_first_response() {
		return ! empty( $this->compatibility_responses ) ? $this->compatibility_responses[0] : null;
	}

	/**
	 * Get compatibility responses by notice type.
	 *
	 * @since 1.0.0
	 * @param string $notice_type Notice type to filter by (error, warning, info, reminder).
	 * @return CompatibilityResponseItem[] Filtered array of response items.
	 */
	public function get_responses_by_notice_type( $notice_type ) {
		$filtered = [];
		foreach ( $this->compatibility_responses as $response ) {
			if ( $response->get_notice_type() === $notice_type ) {
				$filtered[] = $response;
			}
		}
		return $filtered;
	}

	/**
	 * Get compatibility responses by error code.
	 *
	 * @since 1.0.0
	 * @param string $error_code Error code to filter by.
	 * @return CompatibilityResponseItem[] Filtered array of response items.
	 */
	public function get_responses_by_error_code( $error_code ) {
		$filtered = [];
		foreach ( $this->compatibility_responses as $response ) {
			if ( $response->get_error_code() === $error_code ) {
				$filtered[] = $response;
			}
		}
		return $filtered;
	}

	/**
	 * Get error responses.
	 *
	 * @since 1.0.0
	 * @return CompatibilityResponseItem[] Array of error response items.
	 */
	public function get_errors() {
		return $this->get_responses_by_notice_type( 'error' );
	}

	/**
	 * Get warning responses.
	 *
	 * @since 1.0.0
	 * @return CompatibilityResponseItem[] Array of warning response items.
	 */
	public function get_warnings() {
		return $this->get_responses_by_notice_type( 'warning' );
	}

	/**
	 * Get info responses.
	 *
	 * @since 1.0.0
	 * @return CompatibilityResponseItem[] Array of info response items.
	 */
	public function get_info() {
		return $this->get_responses_by_notice_type( 'info' );
	}

	/**
	 * Get reminder responses.
	 *
	 * @since 1.0.0
	 * @return CompatibilityResponseItem[] Array of reminder response items.
	 */
	public function get_reminders() {
		return $this->get_responses_by_notice_type( 'reminder' );
	}

	/**
	 * Check if there are any error responses.
	 *
	 * @since 1.0.0
	 * @return bool True if errors exist, false otherwise.
	 */
	public function has_errors() {
		return ! empty( $this->get_errors() );
	}

	/**
	 * Check if there are any warning responses.
	 *
	 * @since 1.0.0
	 * @return bool True if warnings exist, false otherwise.
	 */
	public function has_warnings() {
		return ! empty( $this->get_warnings() );
	}

	/**
	 * Check if there are any compatibility responses.
	 *
	 * @since 1.0.0
	 * @return bool True if responses exist, false otherwise.
	 */
	public function has_responses() {
		return ! empty( $this->compatibility_responses );
	}

	/**
	 * Get count of compatibility responses.
	 *
	 * @since 1.0.0
	 * @return int Number of compatibility responses.
	 */
	public function count() {
		return count( $this->compatibility_responses );
	}

	/**
	 * Get cache TTL in seconds from API response.
	 *
	 * @since 1.0.0
	 * @return int Cache TTL in seconds, or 0 if not set.
	 */
	public function get_cache_ttl_seconds() {
		return $this->cache_ttl_seconds;
	}

	/**
	 * Check if any response indicates operations should be blocked.
	 *
	 * Operations should be blocked if any response item has enabled set to false.
	 * This is an internal method - use CompatibilityValidator::should_block_operations() for public API.
	 *
	 * @since 1.0.0
	 * @internal
	 * @return bool True if operations should be blocked, false otherwise.
	 */
	public function has_disabled_items() {
		foreach ( $this->compatibility_responses as $response ) {
			if ( ! $response->is_enabled() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Convert to array.
	 *
	 * @since 1.0.0
	 * @return array Array representation of the response.
	 */
	public function to_array() {
		$responses = [];
		foreach ( $this->compatibility_responses as $response ) {
			$responses[] = $response->to_array();
		}

		return [
			'success'                 => $this->success,
			'compatibility_responses' => $responses,
			'cache_ttl_seconds'       => $this->cache_ttl_seconds,
		];
	}
}

