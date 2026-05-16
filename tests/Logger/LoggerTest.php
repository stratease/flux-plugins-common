<?php
/**
 * Tests for suite Logger (wpdb + error_log sink).
 *
 * @package FluxPlugins\Common\Tests\Logger
 */

declare( strict_types=1 );

namespace FluxPlugins\Common\Tests\Logger;

use FluxPlugins\Common\Logger\Logger;
use WorDBless\BaseTestCase;

/**
 * @covers \FluxPlugins\Common\Logger\Logger
 * @covers \FluxPlugins\Common\Logger\DatabaseHandler
 */
class LoggerTest extends BaseTestCase {

	/**
	 * @before
	 */
	public function reset_logger_singleton() {
		Logger::set_error_log_sink_for_tests( null );
		$ref  = new \ReflectionClass( Logger::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
		$slug = $ref->getProperty( 'static_plugin_slug' );
		$slug->setAccessible( true );
		$slug->setValue( 'flux-plugins-common' );
		delete_site_option( 'flux-plugins_common_options' );
	}

	public function test_info_inserts_row_when_logging_enabled() {
		Logger::init( 'test-plugin' );
		Logger::get_instance()->info( 'hello', [ 'k' => 'v' ] );

		global $wpdb;
		$table = $wpdb->prefix . 'flux_plugins_logs';
		$row   = $wpdb->get_row( 'SELECT * FROM `' . esc_sql( $table ) . '` ORDER BY id DESC LIMIT 1', ARRAY_A );
		$this->assertIsArray( $row );
		$this->assertSame( 'test-plugin', $row['plugin_slug'] );
		$this->assertSame( 'INFO', $row['level'] );
		$this->assertSame( 'hello', $row['message'] );
		$this->assertStringContainsString( '"k":"v"', (string) $row['context'] );
	}

	public function test_no_db_insert_when_logging_disabled() {
		update_site_option( 'flux-plugins_common_options', [ 'enable_logging' => false ] );
		Logger::init( 'x' );

		global $wpdb;
		$table = $wpdb->prefix . 'flux_plugins_logs';
		$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`' );

		Logger::get_instance()->info( 'nope' );

		$after = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`' );
		$this->assertSame( $before, $after );
	}

	public function test_error_triggers_error_log_sink() {
		$lines = [];
		Logger::set_error_log_sink_for_tests(
			static function ( $line ) use ( &$lines ) {
				$lines[] = $line;
			}
		);
		Logger::init( 'p' );
		Logger::get_instance()->error( 'boom', [ 'c' => 1 ] );

		$this->assertCount( 1, $lines );
		$this->assertStringContainsString( '[p][ERROR] boom', $lines[0] );
		$this->assertStringContainsString( '"c":1', $lines[0] );
	}

	public function test_error_still_hits_error_log_sink_when_db_logging_disabled() {
		update_site_option( 'flux-plugins_common_options', [ 'enable_logging' => false ] );
		$lines = [];
		Logger::set_error_log_sink_for_tests(
			static function ( $line ) use ( &$lines ) {
				$lines[] = $line;
			}
		);
		Logger::init( 'z' );
		Logger::get_instance()->error( 'still' );

		$this->assertCount( 1, $lines );
		$this->assertStringContainsString( '[z][ERROR] still', $lines[0] );
	}

	public function test_debug_does_not_trigger_error_log_sink() {
		$lines = [];
		Logger::set_error_log_sink_for_tests(
			static function ( $line ) use ( &$lines ) {
				$lines[] = $line;
			}
		);
		Logger::init( 'p' );
		Logger::get_instance()->debug( 'quiet' );

		$this->assertSame( [], $lines );
	}

	public function test_log_accepts_monolog_integer_level() {
		Logger::init( 'lvl' );
		Logger::get_instance()->log( 400, 'e400', [] );

		global $wpdb;
		$table = $wpdb->prefix . 'flux_plugins_logs';
		$row   = $wpdb->get_row( 'SELECT level, message FROM `' . esc_sql( $table ) . '` ORDER BY id DESC LIMIT 1', ARRAY_A );
		$this->assertIsArray( $row );
		$this->assertSame( 'ERROR', $row['level'] );
		$this->assertSame( 'e400', $row['message'] );
	}
}
