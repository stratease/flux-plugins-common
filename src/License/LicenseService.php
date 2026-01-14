<?php
/**
 * License service for managing shared license key across all Flux Plugins.
 *
 * @package FluxPlugins\Common\License
 * @since 1.0.0
 */

namespace FluxPlugins\Common\License;

/**
 * License service.
 *
 * Manages the shared license key and validation state across all Flux Plugins.
 * One license key works for all Flux Plugins in the suite.
 *
 * @since 1.0.0
 */
class LicenseService {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var LicenseService|null
	 */
	private static $instance = null;

	/**
	 * Option name for storing license key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_NAME_LICENSE_KEY = 'flux-plugins_license_key';

	/**
	 * Option name for storing license last valid date.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_NAME_LICENSE_LAST_VALID_DATE = 'flux-plugins_license_last_valid_date';

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return LicenseService Singleton instance.
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Private constructor for singleton pattern.
	}

	/**
	 * Get license key.
	 *
	 * Retrieves the shared license key from site options.
	 *
	 * @since 1.0.0
	 * @return string License key or empty string if not set.
	 */
	public function get_license_key() {
		return (string) get_site_option( self::OPTION_NAME_LICENSE_KEY, '' );
	}

	/**
	 * Set license key.
	 *
	 * Stores the shared license key in site options.
	 *
	 * @since 1.0.0
	 * @param string $license_key License key to store.
	 * @return bool True on success, false on failure.
	 */
	public function set_license_key( $license_key ) {
		return update_site_option( self::OPTION_NAME_LICENSE_KEY, sanitize_text_field( $license_key ) );
	}

	/**
	 * Get license last valid date.
	 *
	 * Retrieves the date when the license was last validated successfully.
	 *
	 * @since 1.0.0
	 * @return string|null MySQL datetime string or null if not set.
	 */
	public function get_license_last_valid_date() {
		$date = get_site_option( self::OPTION_NAME_LICENSE_LAST_VALID_DATE, null );
		return $date ? $date : null;
	}

	/**
	 * Set license last valid date.
	 *
	 * Stores the date when the license was last validated successfully.
	 *
	 * @since 1.0.0
	 * @param string|null $date MySQL datetime string or null to clear.
	 * @return bool True on success, false on failure.
	 */
	public function set_license_last_valid_date( $date ) {
		if ( $date === null ) {
			return delete_site_option( self::OPTION_NAME_LICENSE_LAST_VALID_DATE );
		}
		return update_site_option( self::OPTION_NAME_LICENSE_LAST_VALID_DATE, sanitize_text_field( $date ) );
	}

	/**
	 * Check if license is valid.
	 *
	 * A license is considered valid if:
	 * 1. A license key exists, AND
	 * 2. The license has been validated within the last 24 hours
	 *
	 * @since 1.0.0
	 * @return bool True if license is valid, false otherwise.
	 */
	public function is_license_valid() {
		$license_key = $this->get_license_key();
		
		if ( empty( $license_key ) ) {
			return false;
		}

		$last_valid_date = $this->get_license_last_valid_date();
		
		if ( empty( $last_valid_date ) ) {
			return false;
		}

		// Check if validation date is within last 24 hours
		$last_valid_timestamp = strtotime( $last_valid_date . ' UTC' );
		$current_timestamp = time();
		$hours_since_validation = ( $current_timestamp - $last_valid_timestamp ) / HOUR_IN_SECONDS;

		// License is valid if validated within last 24 hours
		return $hours_since_validation < 24;
	}

	/**
	 * Get license data array.
	 *
	 * Returns all license information in a structured array.
	 *
	 * @since 1.0.0
	 * @return array License data array.
	 */
	public function get_license_data() {
		return [
			'license_key' => $this->get_license_key(),
			'license_last_valid_date' => $this->get_license_last_valid_date(),
			'license_is_valid' => $this->is_license_valid(),
		];
	}

	/**
	 * Transient name for license invalid notice.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TRANSIENT_NAME_LICENSE_INVALID_NOTICE = 'flux-plugins_license_invalid_notice';

	/**
	 * Transient expiration time (24 hours).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TRANSIENT_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Check license validity and update notice transient.
	 *
	 * Checks if license is valid and manages the transient that controls
	 * whether the admin notice should be displayed.
	 *
	 * - If license is invalid and a key exists: Set transient to show notice
	 * - If license is valid: Clear transient to hide notice
	 *
	 * @since 1.0.0
	 * @return bool True if license is valid, false otherwise.
	 */
	public function check_validity_and_update_notice() {
		$license_key = $this->get_license_key();
		$is_valid = $this->is_license_valid();

		// If no license key, no notice needed
		if ( empty( $license_key ) ) {
			$this->clear_invalid_notice_transient();
			return false;
		}

		// Update transient based on validity
		if ( $is_valid ) {
			// License is valid - clear the notice transient
			$this->clear_invalid_notice_transient();
		} else {
			// License is invalid - set transient to show notice
			$this->set_invalid_notice_transient();
		}

		return $is_valid;
	}

	/**
	 * Set transient to indicate license invalid notice should be shown.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	private function set_invalid_notice_transient() {
		return set_site_transient( self::TRANSIENT_NAME_LICENSE_INVALID_NOTICE, true, self::TRANSIENT_EXPIRATION );
	}

	/**
	 * Clear transient to hide license invalid notice.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	private function clear_invalid_notice_transient() {
		return delete_site_transient( self::TRANSIENT_NAME_LICENSE_INVALID_NOTICE );
	}

	/**
	 * Update notice transient based on API validation result.
	 *
	 * This method is called by ExternalApiClient after license validation/activation
	 * to update the notice transient based on the API result, independent of
	 * the current license_last_valid_date state.
	 *
	 * @since 1.0.0
	 * @param bool $is_valid Whether the license is valid according to the API result.
	 * @return void
	 */
	public function update_notice_transient_from_api_result( $is_valid ) {
		// Only update if a license key exists.
		$license_key = $this->get_license_key();
		if ( empty( $license_key ) ) {
			$this->clear_invalid_notice_transient();
			return;
		}

		// Update transient based on API result.
		if ( $is_valid ) {
			$this->clear_invalid_notice_transient();
		} else {
			$this->set_invalid_notice_transient();
		}
	}

	/**
	 * Check if license invalid notice should be displayed.
	 *
	 * @since 1.0.0
	 * @return bool True if notice should be shown, false otherwise.
	 */
	public function should_show_invalid_notice() {
		// Only show if transient exists and license key exists
		$has_transient = get_site_transient( self::TRANSIENT_NAME_LICENSE_INVALID_NOTICE ) !== false;
		$has_license_key = ! empty( $this->get_license_key() );

		return $has_transient && $has_license_key;
	}
}

