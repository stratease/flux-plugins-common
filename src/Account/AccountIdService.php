<?php
/**
 * Account ID service for managing shared account UUID.
 *
 * @package FluxPlugins\Common\Account
 * @since 1.0.0
 */

namespace FluxPlugins\Common\Account;
/**
 * Account ID service.
 *
 * Manages the shared account UUID generation and storage across all Flux Plugins.
 *
 * @since 1.0.0
 */
class AccountIdService {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var AccountIdService|null
	 */
	private static $instance = null;

	/**
	 * Option name for storing account ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_NAME = 'flux-plugins_account_id';

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return AccountIdService Singleton instance.
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
	 * Get account ID.
	 *
	 * Retrieves the account ID from options, generating it if it doesn't exist.
	 *
	 * @since 1.0.0
	 * @return string Account ID (UUID) or empty string if not set.
	 */
	public function get_account_id() {
		$account_id = \get_site_option( self::OPTION_NAME, '' );

		if ( empty( $account_id ) ) {
			$account_id = $this->ensure_account_id();
		}

		return (string) $account_id;
	}

	/**
	 * Ensure account ID exists.
	 *
	 * Generates and stores account ID if it doesn't exist.
	 *
	 * @since 1.0.0
	 * @return string Account ID (UUID).
	 */
	public function ensure_account_id() {
		$account_id = \get_site_option( self::OPTION_NAME, '' );

		if ( empty( $account_id ) ) {
			$account_id = $this->generate_uuid();
			\update_site_option( self::OPTION_NAME, $account_id );
		}

		return $account_id;
	}

	/**
	 * Generate a UUID v4.
	 *
	 * @since 1.0.0
	 * @return string UUID v4 string.
	 */
	private function generate_uuid() {
		// Use WordPress's wp_generate_uuid4() if available (WP 6.1+), otherwise generate manually.
		if ( \function_exists( 'wp_generate_uuid4' ) ) {
			return \wp_generate_uuid4();
		}

		// Fallback UUID v4 generation.
		$data = \random_bytes( 16 );
		$data[6] = \chr( \ord( $data[6] ) & 0x0f | 0x40 ); // Set version to 0100.
		$data[8] = \chr( \ord( $data[8] ) & 0x3f | 0x80 ); // Set bits 6-7 to 10.
		return \vsprintf( '%s%s-%s-%s-%s-%s%s%s', \str_split( \bin2hex( $data ), 4 ) );
	}
}

