<?php
/**
 * Tests for LicenseService admin-notice SSOT (cached validity vs transient write-through).
 *
 * @package FluxPlugins\Common\Tests\License
 */

namespace FluxPlugins\Common\Tests\License;

use FluxPlugins\Common\License\LicenseService;
use WorDBless\BaseTestCase;

/**
 * @covers \FluxPlugins\Common\License\LicenseService
 */
class LicenseServiceNoticeTest extends BaseTestCase {

	/**
	 * @before
	 */
	public function reset_license_service_singleton() {
		$ref  = new \ReflectionClass( LicenseService::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	public function test_should_show_invalid_notice_false_when_no_license_key() {
		delete_site_option( LicenseService::OPTION_NAME_LICENSE_KEY );
		delete_site_option( LicenseService::OPTION_NAME_LICENSE_LAST_VALID_DATE );
		delete_site_transient( LicenseService::TRANSIENT_NAME_LICENSE_INVALID_NOTICE );

		$svc = LicenseService::get_instance();
		$this->assertFalse( $svc->should_show_invalid_notice() );
	}

	public function test_should_show_invalid_notice_true_when_key_and_no_last_valid() {
		update_site_option( LicenseService::OPTION_NAME_LICENSE_KEY, 'test-key-01' );
		delete_site_option( LicenseService::OPTION_NAME_LICENSE_LAST_VALID_DATE );
		delete_site_transient( LicenseService::TRANSIENT_NAME_LICENSE_INVALID_NOTICE );

		$svc = LicenseService::get_instance();
		$this->assertTrue( $svc->should_show_invalid_notice() );
	}

	public function test_should_show_invalid_notice_false_when_cached_valid_even_if_stale_invalid_transient() {
		update_site_option( LicenseService::OPTION_NAME_LICENSE_KEY, 'test-key-02' );
		update_site_option(
			LicenseService::OPTION_NAME_LICENSE_LAST_VALID_DATE,
			gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS )
		);
		set_site_transient( LicenseService::TRANSIENT_NAME_LICENSE_INVALID_NOTICE, true, DAY_IN_SECONDS );

		$svc = LicenseService::get_instance();
		$this->assertFalse( $svc->should_show_invalid_notice() );
		$this->assertFalse( get_site_transient( LicenseService::TRANSIENT_NAME_LICENSE_INVALID_NOTICE ) !== false );
	}

	public function test_should_show_invalid_notice_true_when_cache_expired() {
		update_site_option( LicenseService::OPTION_NAME_LICENSE_KEY, 'test-key-03' );
		update_site_option(
			LicenseService::OPTION_NAME_LICENSE_LAST_VALID_DATE,
			gmdate( 'Y-m-d H:i:s', time() - ( 25 * HOUR_IN_SECONDS ) )
		);
		delete_site_transient( LicenseService::TRANSIENT_NAME_LICENSE_INVALID_NOTICE );

		$svc = LicenseService::get_instance();
		$this->assertTrue( $svc->should_show_invalid_notice() );
	}

	public function test_check_validity_and_update_notice_clears_transient_when_cached_valid() {
		update_site_option( LicenseService::OPTION_NAME_LICENSE_KEY, 'test-key-04' );
		update_site_option(
			LicenseService::OPTION_NAME_LICENSE_LAST_VALID_DATE,
			gmdate( 'Y-m-d H:i:s', time() - ( 2 * HOUR_IN_SECONDS ) )
		);
		set_site_transient( LicenseService::TRANSIENT_NAME_LICENSE_INVALID_NOTICE, true, DAY_IN_SECONDS );

		$svc = LicenseService::get_instance();
		$this->assertTrue( $svc->check_validity_and_update_notice() );
		$this->assertFalse( get_site_transient( LicenseService::TRANSIENT_NAME_LICENSE_INVALID_NOTICE ) !== false );
	}

	public function test_check_validity_and_update_notice_sets_transient_when_cached_invalid() {
		update_site_option( LicenseService::OPTION_NAME_LICENSE_KEY, 'test-key-05' );
		update_site_option(
			LicenseService::OPTION_NAME_LICENSE_LAST_VALID_DATE,
			gmdate( 'Y-m-d H:i:s', time() - ( 25 * HOUR_IN_SECONDS ) )
		);
		delete_site_transient( LicenseService::TRANSIENT_NAME_LICENSE_INVALID_NOTICE );

		$svc = LicenseService::get_instance();
		$this->assertFalse( $svc->check_validity_and_update_notice() );
		$this->assertTrue( get_site_transient( LicenseService::TRANSIENT_NAME_LICENSE_INVALID_NOTICE ) !== false );
	}

	public function test_check_validity_and_update_notice_clears_transient_when_no_key() {
		delete_site_option( LicenseService::OPTION_NAME_LICENSE_KEY );
		set_site_transient( LicenseService::TRANSIENT_NAME_LICENSE_INVALID_NOTICE, true, DAY_IN_SECONDS );

		$svc = LicenseService::get_instance();
		$this->assertFalse( $svc->check_validity_and_update_notice() );
		$this->assertFalse( get_site_transient( LicenseService::TRANSIENT_NAME_LICENSE_INVALID_NOTICE ) !== false );
	}
}
