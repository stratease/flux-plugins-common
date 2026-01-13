<?php
/**
 * Logs REST API controller for Flux Plugins Common.
 *
 * @package FluxPlugins\Common\Http\Controllers
 * @since 1.0.0
 */

namespace FluxPlugins\Common\Http\Controllers;

use FluxPlugins\Common\Logger\Logger;
use FluxPlugins\Common\Services\LogsService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Logs REST API controller.
 *
 * Handles logs-related REST API endpoints for all Flux Plugins.
 *
 * @since 1.0.0
 */
class LogsController {

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Logs service instance.
	 *
	 * @since 1.0.0
	 * @var LogsService
	 */
	private $logs_service;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Logger $logger Logger instance (required).
	 */
	public function __construct( Logger $logger ) {
		if ( ! $logger ) {
			throw new \InvalidArgumentException( 'Logger instance is required' );
		}
		$this->logger = $logger;
		$this->logs_service = new LogsService();
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route( 'flux-plugins-common/v1', '/logs', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'get_logs' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );
	}

	/**
	 * Get logs.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_logs( WP_REST_Request $request ) {
		try {
			$args = [
				'page' => $request->get_param( 'page' ) ?: 1,
				'per_page' => $request->get_param( 'per_page' ) ?: 20,
				'level' => $request->get_param( 'level' ),
				'search' => $request->get_param( 'search' ),
				'plugin_slug' => $request->get_param( 'plugin_slug' ),
			];
			
			$logs = $this->logs_service->get_logs( $args );

			return $this->create_success_response( $logs, 'Logs retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 'Failed to retrieve logs: ' . $e->getMessage() );
		}
	}

	/**
	 * Check if user has permission to access logs.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if user has permission.
	 */
	public function check_permissions( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Create success response.
	 *
	 * @since 1.0.0
	 * @param mixed  $data    Response data.
	 * @param string $message Success message.
	 * @return WP_REST_Response Response object.
	 */
	private function create_success_response( $data, $message = '' ) {
		$response = [
			'success' => true,
			'data' => $data,
		];

		if ( ! empty( $message ) ) {
			$response['message'] = $message;
		}

		$response['timestamp'] = current_time( 'mysql', true );

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Create error response.
	 *
	 * @since 1.0.0
	 * @param string $message   Error message.
	 * @param string $error_code Optional error code.
	 * @param int    $status_code Optional HTTP status code.
	 * @return WP_REST_Response Response object.
	 */
	private function create_error_response( $message, $error_code = 'unknown_error', $status_code = 500 ) {
		$response = [
			'success' => false,
			'error' => [
				'code' => $error_code,
				'message' => $message,
			],
		];

		$response['timestamp'] = current_time( 'mysql', true );

		return new WP_REST_Response( $response, $status_code );
	}
}

