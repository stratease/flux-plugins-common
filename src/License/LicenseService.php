<?php
/**
 * License service for managing shared license key across all Flux Plugins.
 *
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * @package FluxPlugins\Common\License
 * @since 1.0.0
 * @since 1.0.0 Added externally managed source notice.
 */

namespace FluxPlugins\Common\License;

use FluxPlugins\Common\Api\ExternalApiClient;
use FluxPlugins\Common\Logger\Logger;
use FluxPlugins\Common\Account\AccountIdService;

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
	 * If the cache check fails (expired or missing), this method will automatically
	 * attempt to re-validate via the API to update the cache.
	 *
	 * @since 1.0.0
	 * @param bool $auto_validate Whether to automatically validate if cache is expired. Default true.
	 * @return bool True if license is valid, false otherwise.
	 */
	public function is_license_valid( $auto_validate = true ) {
		$license_key = $this->get_license_key();
		
		if ( empty( $license_key ) ) {
			return false;
		}

		$last_valid_date = $this->get_license_last_valid_date();
		
		// If no validation date, try auto-validation if enabled
		if ( empty( $last_valid_date ) ) {
			if ( $auto_validate ) {
				$this->maybe_auto_validate();
				// Check again after auto-validation
				$last_valid_date = $this->get_license_last_valid_date();
				if ( empty( $last_valid_date ) ) {
					return false;
				}
			} else {
				return false;
			}
		}

		// Check if validation date is within last 24 hours using WordPress timezone functions
		$last_valid_timestamp = mysql2date( 'U', $last_valid_date, false ); // false = GMT/UTC
		$current_timestamp = current_time( 'timestamp', true ); // true = GMT/UTC
		$hours_since_validation = ( $current_timestamp - $last_valid_timestamp ) / HOUR_IN_SECONDS;

		// If cache expired and auto-validation is enabled, try to re-validate
		if ( $hours_since_validation >= 24 && $auto_validate ) {
			$this->maybe_auto_validate();
			// Re-check after auto-validation
			$last_valid_date = $this->get_license_last_valid_date();
			if ( empty( $last_valid_date ) ) {
				return false;
			}
			$last_valid_timestamp = mysql2date( 'U', $last_valid_date, false );
			$current_timestamp = current_time( 'timestamp', true );
			$hours_since_validation = ( $current_timestamp - $last_valid_timestamp ) / HOUR_IN_SECONDS;
		}

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
	 * Transient name for auto-validation lock.
	 * Prevents multiple simultaneous auto-validations.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const TRANSIENT_NAME_AUTO_VALIDATION_LOCK = 'flux-plugins_license_auto_validation_lock';

	/**
	 * Auto-validation lock duration (5 minutes).
	 *
	 * @since 1.1.0
	 * @var int
	 */
	const AUTO_VALIDATION_LOCK_DURATION = 300;

	/**
	 * Whether the license is valid from persisted cache only (no remote auto-validation).
	 *
	 * Single source of truth for read paths that must not trigger HTTP (admin notice, transient sync).
	 *
	 * @since 1.2.0
	 * @return bool True when a key exists, last_valid_date is set, and within the 24-hour window.
	 */
	private function is_cached_license_valid() {
		return $this->is_license_valid( false );
	}

	/**
	 * Check license validity and update notice transient.
	 *
	 * Syncs the invalid-notice transient to match {@see is_cached_license_valid()} without calling
	 * remote auto-validation. Return value matches that cached validity (not `is_license_valid( true )`).
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Uses cached validity only; aligns transient with SSOT (no surprise remote calls).
	 * @return bool True when cached license is valid within the 24-hour window.
	 */
	public function check_validity_and_update_notice() {
		$license_key = $this->get_license_key();

		if ( empty( $license_key ) ) {
			$this->clear_invalid_notice_transient();
			return false;
		}

		$cached_valid = $this->is_cached_license_valid();

		if ( $cached_valid ) {
			$this->clear_invalid_notice_transient();
		} else {
			$this->set_invalid_notice_transient();
		}

		return $cached_valid;
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
	 * Derived from the same cached rule as cloud service eligibility: a key must exist and
	 * {@see is_cached_license_valid()} must be false. Does not read the invalid-notice transient
	 * for the decision; that transient remains a write-through mirror updated from API results
	 * and from {@see check_validity_and_update_notice()}. When cached validity is true, the stale
	 * transient is cleared so DB state matches.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 SSOT: derive from cached validity + key instead of transient read.
	 * @return bool True if notice should be shown, false otherwise.
	 */
	public function should_show_invalid_notice() {
		if ( empty( $this->get_license_key() ) ) {
			return false;
		}

		if ( $this->is_cached_license_valid() ) {
			$this->update_notice_transient_from_api_result( true );
			return false;
		}

		return true;
	}

	/**
	 * Automatically validate license if cache is expired.
	 *
	 * Uses a transient lock to prevent multiple simultaneous validations.
	 * Only attempts validation if we're in a context where API calls are possible
	 * (REST API, admin context, or cron).
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function maybe_auto_validate() {
		$license_key = $this->get_license_key();
		
		// Only validate if we have a license key
		if ( empty( $license_key ) ) {
			return;
		}

		// Check if auto-validation is already in progress (prevent concurrent requests)
		$lock = get_site_transient( self::TRANSIENT_NAME_AUTO_VALIDATION_LOCK );
		if ( $lock !== false ) {
			return;
		}

		// Only attempt auto-validation in contexts where HTTP requests are possible
		// Skip during early WordPress loading when functions might not be available
		if ( ! did_action( 'plugins_loaded' ) ) {
			return;
		}

		// Set lock to prevent concurrent validations
		set_site_transient( self::TRANSIENT_NAME_AUTO_VALIDATION_LOCK, true, self::AUTO_VALIDATION_LOCK_DURATION );

		$logger = Logger::get_instance();
		$api_client = new ExternalApiClient( $logger );
		$validation_result = $api_client->validate_license( $license_key );

		// Update cache based on validation result
		if ( $validation_result && is_array( $validation_result ) ) {
			if ( isset( $validation_result['success'] ) && $validation_result['success'] && isset( $validation_result['valid'] ) && $validation_result['valid'] ) {
				// License is valid - update cache
				$this->set_license_last_valid_date( current_time( 'mysql', true ) );
			} else {
				// License is invalid - clear cache
				$this->set_license_last_valid_date( null );
			}
		}

		// Remove lock after validation attempt
		delete_site_transient( self::TRANSIENT_NAME_AUTO_VALIDATION_LOCK );
	}
}

