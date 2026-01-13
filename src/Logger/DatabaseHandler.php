<?php
/**
 * Database handler for Monolog logger.
 *
 * @package FluxPlugins\Common\Logger
 * @since 1.0.0
 */

namespace FluxPlugins\Common\Logger;

use Monolog\Handler\AbstractProcessingHandler;

/**
 * Database handler for storing logs in WordPress database.
 *
 * @since 1.0.0
 */
class DatabaseHandler extends AbstractProcessingHandler {

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
	 * @param string $plugin_slug Plugin slug.
	 * @param int    $level       The minimum logging level at which this handler will be triggered.
	 * @param bool   $bubble      Whether the messages that are handled can bubble up the stack or not.
	 */
	public function __construct( $plugin_slug, $level = \Monolog\Logger::DEBUG, $bubble = true ) {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'flux_plugins_logs';
		$this->plugin_slug = $plugin_slug;
		parent::__construct( $level, $bubble );
		$this->maybe_create_table();
	}

	/**
	 * Write the log record to the database.
	 *
	 * @since 1.0.0
	 * @param array $record The log record to write.
	 */
	protected function write( array $record ): void {
		global $wpdb;

		// Check if logging is disabled via common library option
		$options = get_site_option( 'flux-plugins_common_options', [] );
		if ( ! ( $options['enable_logging'] ?? true ) ) {
			return;
		}

		$wpdb->insert(
			$this->table_name,
			[
				'plugin_slug' => $this->plugin_slug,
				'level'       => $record['level_name'],
				'message'     => $record['message'],
				'context'     => ! empty( $record['context'] ) ? wp_json_encode( $record['context'] ) : null,
				'created_at'  => $record['datetime']->format( 'Y-m-d H:i:s' ),
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

