<?php
/**
 * Persists suite log rows to the WordPress database.
 *
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * @package FluxPlugins\Common\Logger
 * @since 1.0.0
 * @since 1.0.0 Added externally managed source notice.
 * @since 1.2.0 Replaced Monolog handler with direct wpdb persistence.
 */

namespace FluxPlugins\Common\Logger;

/**
 * Database writer for suite log entries (`{prefix}flux_plugins_logs`).
 *
 * @since 1.0.0
 */
class DatabaseHandler {

	/**
	 * Database table name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Plugin slug for this handler instance.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Removed Monolog level and bubble parameters.
	 *
	 * @param string $plugin_slug Plugin slug.
	 */
	public function __construct( $plugin_slug ) {
		global $wpdb;
		$this->table_name  = $wpdb->prefix . 'flux_plugins_logs';
		$this->plugin_slug = $plugin_slug;
		$this->maybe_create_table();
	}

	/**
	 * Insert a log row when suite logging is enabled.
	 *
	 * @since 1.2.0
	 *
	 * @param string               $level_name Level label (e.g. DEBUG, ERROR).
	 * @param string               $message    Log message.
	 * @param array                $context    Structured context.
	 * @param \DateTimeInterface|null $datetime Optional timestamp; defaults to WordPress local time.
	 * @return void
	 */
	public function persist( $level_name, $message, array $context, $datetime = null ): void {
		global $wpdb;

		$options = get_site_option( 'flux-plugins_common_options', [] );
		if ( ! ( $options['enable_logging'] ?? true ) ) {
			return;
		}

		$created_at = null;
		if ( $datetime instanceof \DateTimeInterface ) {
			$created_at = $datetime->format( 'Y-m-d H:i:s' );
		} else {
			$created_at = function_exists( 'current_time' )
				? current_time( 'mysql' )
				: gmdate( 'Y-m-d H:i:s' );
		}

		$wpdb->insert(
			$this->table_name,
			[
				'plugin_slug' => $this->plugin_slug,
				'level'       => (string) $level_name,
				'message'     => (string) $message,
				'context'     => ! empty( $context ) ? wp_json_encode( $context ) : null,
				'created_at'  => $created_at,
			],
			[
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);
	}

	/**
	 * Maybe create logs table.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function maybe_create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			plugin_slug varchar(100) NOT NULL,
			level varchar(20) NOT NULL,
			message text NOT NULL,
			context longtext,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY plugin_slug (plugin_slug),
			KEY level (level),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
