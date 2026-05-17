<?php
/**
 * Tests for FluxPlugins license admin notice screen scope.
 *
 * @package FluxPlugins\Common\Tests\FluxPlugins
 */

namespace FluxPlugins\Common\Tests\FluxPlugins;

use FluxPlugins\Common\FluxPlugins;
use WorDBless\BaseTestCase;

/**
 * @covers \FluxPlugins\Common\FluxPlugins::should_show_license_notice_on_screen
 */
class FluxPluginsNoticeScreenTest extends BaseTestCase {

	/**
	 * @before
	 */
	public function reset_flux_plugins_singleton() {
		$ref  = new \ReflectionClass( FluxPlugins::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	/**
	 * @after
	 */
	public function tear_down_notice_screen_test() {
		remove_all_filters( 'flux_suite/license_service/show_admin_notice_on_screen' );
		if ( function_exists( 'set_current_screen' ) ) {
			set_current_screen( 'front' );
		}
	}

	public function test_should_show_license_notice_on_screen_false_when_no_screen() {
		FluxPlugins::init( 'flux-test-plugin', '1.0.0', 'flux-test-plugin' );
		$this->assertFalse( FluxPlugins::get_instance()->should_show_license_notice_on_screen() );
	}

	public function test_should_show_license_notice_on_screen_true_for_flux_suite_screen() {
		FluxPlugins::init( 'flux-test-plugin', '1.0.0', 'flux-test-plugin' );
		set_current_screen( 'flux-suite' );
		$this->assertTrue( FluxPlugins::get_instance()->should_show_license_notice_on_screen() );
	}

	public function test_should_show_license_notice_on_screen_true_for_host_plugin_screen() {
		FluxPlugins::init( 'flux-test-plugin', '1.0.0', 'flux-test-plugin' );
		set_current_screen( 'toplevel_page_flux-test-plugin' );
		$this->assertTrue( FluxPlugins::get_instance()->should_show_license_notice_on_screen() );
	}

	public function test_filter_can_enable_notice_on_screen() {
		FluxPlugins::init( 'flux-test-plugin', '1.0.0', 'flux-test-plugin' );
		set_current_screen( 'dashboard' );
		add_filter( 'flux_suite/license_service/show_admin_notice_on_screen', '__return_true' );
		$result = FluxPlugins::get_instance()->should_show_license_notice_on_screen();
		$this->assertTrue( $result );
	}

	public function test_filter_defaults_false_for_unknown_screen() {
		FluxPlugins::init( 'flux-test-plugin', '1.0.0', 'flux-test-plugin' );
		set_current_screen( 'dashboard' );
		$this->assertFalse( FluxPlugins::get_instance()->should_show_license_notice_on_screen() );
	}
}
