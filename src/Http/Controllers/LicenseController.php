<?php
/**
 * License REST API controller for Flux Plugins Common.
 *
 * @package FluxPlugins\Common\Http\Controllers
 * @since 1.0.0
 */

namespace FluxPlugins\Common\Http\Controllers;

use FluxPlugins\Common\License\LicenseService;
use FluxPlugins\Common\Api\ExternalApiClient;
use WP_REST_Request;
use WP_REST_Response;

/**
 * License REST API controller.
 *
 * Handles license-related REST API endpoints for all Flux Plugins.
 * License is shared across all plugins in the suite.
 *
 * @since 1.0.0
 */
class LicenseController {

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var \Psr\Log\LoggerInterface|null
	 */
	private $logger;

	/**
	 * License service instance.
	 *
	 * @since 1.0.0
	 * @var LicenseService
	 */
	private $license_service;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param \Psr\Log\LoggerInterface|null $logger Optional logger instance.
	 */
	public function __construct( $logger = null ) {
		$this->logger = $logger;
		$this->license_service = LicenseService::get_instance();
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route( 'flux-plugins-common/v1', '/license', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'get_license' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );

		register_rest_route( 'flux-plugins-common/v1', '/license/activate', [
			[
				'methods' => 'POST',
				'callback' => [ $this, 'activate_license' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args' => [
					'license_key' => [
						'required' => true,
						'type' => 'string',
						'description' => 'License key to activate',
					],
				],
			],
		] );

		register_rest_route( 'flux-plugins-common/v1', '/license/validate', [
			[
				'methods' => 'POST',
				'callback' => [ $this, 'validate_license' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );
	}

	/**
	 * Get license information.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_license( WP_REST_Request $request ) {
		try {
			$license_data = $this->license_service->get_license_data();
			
			return $this->create_success_response( $license_data, 'License information retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 'Failed to retrieve license information: ' . $e->getMessage() );
		}
	}

	/**
	 * Activate license key.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function activate_license( WP_REST_Request $request ) {
		try {
			$license_key = $request->get_param( 'license_key' );
			
			if ( empty( $license_key ) ) {
				return $this->create_error_response( 'License key is required', 'license_key_required', 400 );
			}

			// Get current plugin version from request context or use empty string
			// The plugin slug/version can be determined from the requesting plugin if needed
			$plugin_version = '';
			
			// Create API client
			$api_client = new ExternalApiClient( $this->logger );
			$activation_result = $api_client->activate_license( $license_key, $plugin_version );

			// Save the license key regardless of activation result
			$this->license_service->set_license_key( $license_key );

			// Format error message based on debug mode if activation failed
			if ( ! $activation_result['success'] ) {
				// Clear validation date on failure
				$this->license_service->set_license_last_valid_date( null );
				$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
				
				$error_code = $activation_result['error'] ?? 'unknown_error';
				$error_message = $activation_result['message'] ?? 'License activation failed';
				$status_code = $activation_result['status_code'] ?? null;
				
				if ( $is_debug ) {
					// Debug mode: Include detailed error information
					$response_details = wp_json_encode( $activation_result, JSON_PRETTY_PRINT );
					if ( $status_code ) {
						$formatted_message = sprintf(
							'%s %s error: %s. Response: %s',
							$status_code,
							$error_code,
							$error_message,
							$response_details
						);
					} else {
						$formatted_message = sprintf(
							'%s error: %s. Response: %s',
							$error_code,
							$error_message,
							$response_details
						);
					}
				} else {
					// Non-debug mode: Simple user-friendly message
					$user_messages = [
						'network_error' => __( 'Network error', 'flux-plugins-common' ),
						'license_invalid' => __( 'Invalid license key', 'flux-plugins-common' ),
						'license_expired' => __( 'License has expired', 'flux-plugins-common' ),
						'license_inactive' => __( 'License is inactive', 'flux-plugins-common' ),
						'activation_failed' => __( 'License activation failed', 'flux-plugins-common' ),
						'validation_failed' => __( 'Invalid request', 'flux-plugins-common' ),
						'account_id_required' => __( 'Account ID not found', 'flux-plugins-common' ),
						'internal_error' => __( 'Server error', 'flux-plugins-common' ),
						'unknown_error' => __( 'Unknown error', 'flux-plugins-common' ),
					];
					
					$formatted_message = $user_messages[ $error_code ] ?? __( 'License activation failed', 'flux-plugins-common' );
				}
				
				// Return error response with formatted message
				// Use 400 status code for client errors (license issues)
				$http_status = in_array( $error_code, [ 'license_invalid', 'license_expired', 'license_inactive', 'validation_failed' ], true ) ? 400 : 500;
				
				return $this->create_error_response( 
					substr( $formatted_message, 0, 600 ),
					$error_code,
					$http_status
				);
			}

			// Activation successful - mark as valid and save validation date
			$this->license_service->set_license_last_valid_date( current_time( 'mysql', true ) );

			$response_data = $this->license_service->get_license_data();

			return $this->create_success_response( $response_data, 'License activated successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 'Failed to activate license: ' . $e->getMessage() );
		}
	}

	/**
	 * Validate license key.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function validate_license( WP_REST_Request $request ) {
		try {
			$license_key = $this->license_service->get_license_key();
			
			if ( empty( $license_key ) ) {
				return $this->create_error_response( 'No license key found', 'license_key_not_found', 400 );
			}

			// Create API client and validate
			$api_client = new ExternalApiClient( $this->logger );
			$validation_result = $api_client->validate_license( $license_key );

			if ( $validation_result['success'] && $validation_result['valid'] ) {
				// Update validation date on successful validation
				$this->license_service->set_license_last_valid_date( current_time( 'mysql', true ) );
			}

			$response_data = $this->license_service->get_license_data();

			return $this->create_success_response( $response_data, 'License validation completed' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 'Failed to validate license: ' . $e->getMessage() );
		}
	}

	/**
	 * Check if user has permission to access license endpoints.
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

