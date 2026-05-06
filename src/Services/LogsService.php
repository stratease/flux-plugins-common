<?php
/**
 * Logs service for retrieving log entries.
 *
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * @package FluxPlugins\Common\Services
 * @since 1.0.0
 * @since 1.0.0 Added externally managed source notice.
 */

namespace FluxPlugins\Common\Services;

/**
 * Logs service.
 *
 * Retrieves log entries from the database.
 *
 * @since 1.0.0
 */
class LogsService {

	/**
	 * Table name for logs.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TABLE_NAME = 'flux_plugins_logs';

	/**
	 * Get logs with pagination and filtering.
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments.
	 * @return array Logs data with pagination info.
	 */
	public function get_logs( $args = [] ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$defaults = [
			'page' => 1,
			'per_page' => 20,
			'level' => '',
			'search' => '',
			'plugin_slug' => '',
			'orderby' => 'created_at',
			'order' => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause
		$where_conditions = [];
		$where_values = [];

		if ( ! empty( $args['level'] ) ) {
			$where_conditions[] = 'level = %s';
			$where_values[] = $args['level'];
		}

		if ( ! empty( $args['plugin_slug'] ) ) {
			$where_conditions[] = 'plugin_slug = %s';
			$where_values[] = $args['plugin_slug'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where_conditions[] = '(message LIKE %s OR context LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
		}

		$where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

		// Get total count
		if ( ! empty( $where_values ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 
				"SELECT COUNT(*) FROM `" . esc_sql( $table_name ) . "` {$where_clause}", 
				$where_values 
			) );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `" . esc_sql( $table_name ) . "`" );
		}

		// Calculate pagination
		$offset = ( $args['page'] - 1 ) * $args['per_page'];
		$total_pages = ceil( $total / $args['per_page'] );

		// Get logs
		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		$query_values = array_merge( $where_values, [ $args['per_page'], $offset ] );
		$logs = $wpdb->get_results( $wpdb->prepare( 
			"SELECT id, plugin_slug, level, message, context, created_at FROM `" . esc_sql( $table_name ) . "` {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d", 
			$query_values 
		), ARRAY_A );

		// Process logs
		foreach ( $logs as &$log ) {
			$log['context'] = ! empty( $log['context'] ) ? json_decode( $log['context'], true ) : null;
		}

		return [
			'data' => $logs,
			'total' => $total,
			'page' => $args['page'],
			'per_page' => $args['per_page'],
			'total_pages' => $total_pages,
		];
	}

	/**
	 * Get log levels available in the database.
	 *
	 * @since 1.0.0
	 * @return array Array of log levels.
	 */
	public function get_log_levels() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$levels = $wpdb->get_col( "SELECT DISTINCT level FROM `" . esc_sql( $table_name ) . "` ORDER BY level" );
		return $levels ?: [];
	}

	/**
	 * Clear old logs.
	 *
	 * @since 1.0.0
	 * @param int $days Number of days to keep logs.
	 * @return int Number of logs deleted.
	 */
	public function clear_old_logs( $days = 30 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM `" . esc_sql( $table_name ) . "` WHERE created_at < %s",
			$cutoff_date
		) );

		return $deleted ?: 0;
	}
}

