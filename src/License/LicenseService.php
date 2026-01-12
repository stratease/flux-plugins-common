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
}

