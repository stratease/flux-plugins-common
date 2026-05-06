<?php
/**
 * External API client for Flux Plugins suite.
 *
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * Shared API client for license validation, activation, and compatibility checks.
 * Plugin-specific endpoints (with namespace like 'fmo') remain as wrapper methods.
 *
 * @package FluxPlugins\Common\Api
 * @since 1.0.0
 * @since 1.0.0 Added externally managed source notice.
 */

namespace FluxPlugins\Common\Api;

use FluxPlugins\Common\Account\AccountIdService;
use FluxPlugins\Common\Compatibility\CompatibilityResponse;
use FluxPlugins\Common\Logger\Logger;
use FluxPlugins\Common\License\LicenseService;
use FluxPlugins\Common\Services\CompatibilityService;

/**
 * External API client.
 *
 * Handles communication with external Flux Plugins API service.
 * Provides full implementation for shared endpoints (activate, validate, compatibility)
 * and generic wrappers for plugin-specific endpoints.
 *
 * @since 1.0.0
 */
class ExternalApiClient {

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * External service base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $base_url;

	/**
	 * Request timeout in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $timeout;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Logger $logger   Logger instance (required).
	 * @param string|null              $base_url Optional external service base URL. If not provided,
	 *                                           will check FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL constant,
	 *                                           or fallback to default 'https://api.fluxplugins.com'.
	 * @param int|null                 $timeout  Optional request timeout in seconds. If not provided,
	 *                                           will check FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT constant,
	 *                                           or fallback to default 15.
	 */
	public function __construct( Logger $logger, $base_url = null, $timeout = null ) {
		$this->logger = $logger;

		// Initialize base URL: use provided value, or check constant, or use default.
		if ( $base_url !== null ) {
			$this->base_url = $base_url;
		} elseif ( defined( 'FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL' ) ) {
			$this->base_url = \FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL;
		} else {
			$this->base_url = 'https://api.fluxplugins.com';
		}

		// Initialize timeout: use provided value, or check constant, or use default.
		if ( $timeout !== null ) {
			$this->timeout = $timeout;
		} elseif ( defined( 'FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT' ) ) {
			$this->timeout = (int) \FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT;
		} else {
			$this->timeout = 15;
		}
	}


	/**
	 * Activate license key with external service.
	 *
	 * This is the activation request that sends license_key, account_id, website domain, and plugin version.
	 * Should be called when license_key changes or is initially set.
	 * All subsequent requests use only account_id.
	 *
	 * Automatically checks compatibility before making the request. If compatibility check fails,
	 * the request is blocked and an error response is returned.
	 *
	 * @since 1.0.0
	 * @param string $license_key License key to activate.
	 * @param string $plugin_version Optional plugin version (if not provided, will not be included).
	 * @return array Response array with 'success', 'valid', 'error', and 'message'.
	 */
	public function activate_license( $license_key, $plugin_version = '' ) {
		// Check compatibility before making API request.
		if ( ! $this->check_compatibility_before_request() ) {
			$this->logger->warning( 'License activation blocked: Compatibility check indicates operations are disabled' );
			$result = [
				'success' => false,
				'error'   => 'compatibility_check_failed',
				'message' => 'Compatibility check failed. Please update the plugin or check compatibility status.',
			];
			// Update notice transient for failed activation.
			$this->update_license_notice_transient( $result );
			return $result;
		}

		$account_id = AccountIdService::get_instance()->get_account_id();

		if ( empty( $account_id ) ) {
			$this->logger->error( 'License activation failed: Account ID not found' );
			$result = [
				'success' => false,
				'error'   => 'account_id_required',
				'message' => 'Account ID not found',
			];
			// Update notice transient for failed activation.
			$this->update_license_notice_transient( $result );
			return $result;
		}

		// Get website domain - use full URL as the endpoint expects a URL format.
		$website_domain = home_url();
		if ( empty( $website_domain ) ) {
			$protocol        = is_ssl() ? 'https://' : 'http://';
			$website_domain  = $protocol . ( $_SERVER['HTTP_HOST'] ?? 'localhost' );
		}

		$endpoint = trailingslashit( $this->base_url ) . 'api/v1/licenses/activate';

		$request_body = [
			'license_key' => sanitize_text_field( $license_key ),
			'account_id'  => $account_id,
			'domain'      => esc_url_raw( $website_domain ),
		];

		// Include plugin_version if available.
		if ( ! empty( $plugin_version ) ) {
			$request_body['plugin_version'] = sanitize_text_field( $plugin_version );
		}

		$this->logger->debug( "Activating license for account " . AccountIdService::get_instance()->obfuscate_account_id() . ", domain: {$website_domain}" );

		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => $this->timeout,
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $request_body ),
			]
		);

		$result = $this->handle_license_response( $response, 'activation', $account_id );

		// Update license validation notice transient based on result.
		// This is the single source of truth for license validation status.
		$this->update_license_notice_transient( $result );

		return $result;
	}

	/**
	 * Validate license key with external service.
	 *
	 * Checks if the current license key is still valid.
	 * Should be called periodically to verify license status.
	 *
	 * Automatically checks compatibility before making the request. If compatibility check fails,
	 * the request is blocked and an error response is returned.
	 *
	 * @since 1.0.0
	 * @param string $license_key License key to validate.
	 * @return array Response array with 'success', 'valid', 'error', and 'message'.
	 */
	public function validate_license( $license_key ) {
		// Check compatibility before making API request.
		if ( ! $this->check_compatibility_before_request() ) {
			$this->logger->warning( 'License validation blocked: Compatibility check indicates operations are disabled' );
			$result = [
				'success'     => false,
				'error'       => 'compatibility_check_failed',
				'message'     => 'Compatibility check failed. Please update the plugin or check compatibility status.',
				'status_code' => null,
			];
			// Update notice transient for failed validation.
			$this->update_license_notice_transient( $result );
			return $result;
		}

		$account_id = AccountIdService::get_instance()->get_account_id();

		if ( empty( $account_id ) ) {
			$this->logger->error( 'License validation failed: Account ID not found' );
			$result = [
				'success'     => false,
				'error'       => 'account_id_required',
				'message'     => 'Account ID not found',
				'status_code' => null,
			];
			// Update notice transient for failed validation.
			$this->update_license_notice_transient( $result );
			return $result;
		}

		// Get website domain - use full URL as the endpoint expects a URL format.
		$website_domain = home_url();
		if ( empty( $website_domain ) ) {
			$protocol       = is_ssl() ? 'https://' : 'http://';
			$website_domain = $protocol . ( $_SERVER['HTTP_HOST'] ?? 'localhost' );
		}

		$endpoint = trailingslashit( $this->base_url ) . 'api/v1/licenses/validate';

		$request_body = [
			'license_key' => sanitize_text_field( $license_key ),
			'account_id'  => $account_id,
			'domain'      => esc_url_raw( $website_domain ),
		];

		$this->logger->debug( "Validating license for account " . AccountIdService::get_instance()->obfuscate_account_id() . ", domain: {$website_domain}" );

		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => $this->timeout,
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $request_body ),
			]
		);

		$result = $this->handle_license_response( $response, 'validation', $account_id );

		// Update license validation notice transient based on result.
		// This is the single source of truth for license validation status.
		$this->update_license_notice_transient( $result );

		return $result;
	}

	/**
	 * Check plugin compatibility with external service.
	 *
	 * This endpoint validates version compatibility between the plugin and API service.
	 * It is independent of license validation and focuses solely on version requirements.
	 *
	 * @since 1.0.0
	 * @param string $plugin_identifier Plugin identifier (e.g., 'flux-media-optimizer').
	 * @param string $plugin_version   Current plugin version.
	 * @return CompatibilityResponse|array Response object or array with 'success' and error info on failure.
	 */
	public function check_compatibility( $plugin_identifier, $plugin_version ) {
		$endpoint = trailingslashit( $this->base_url ) . 'api/v1/compatibility/check';

		$request_body = [
			'plugin_identifier' => sanitize_text_field( $plugin_identifier ),
			'plugin_version'    => sanitize_text_field( $plugin_version ),
		];

		$this->logger->debug( "Checking compatibility for plugin {$plugin_identifier} version {$plugin_version}" );

		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => $this->timeout,
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $request_body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->logger->warning( "Compatibility check network error: {$error_message}" );
			return [
				'success' => false,
				'error'   => 'network_error',
				'message' => $error_message,
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		// Handle validation errors (422) - unexpected, indicates a bug in our request.
		if ( $status_code === 422 ) {
			$error   = isset( $data['error'] ) ? $data['error'] : 'validation_failed';
			$message = isset( $data['message'] ) ? $data['message'] : 'Invalid request parameters';
			$errors  = isset( $data['errors'] ) ? $data['errors'] : [];

			$this->logger->error( "Compatibility check validation failed: {$message}", [ 'errors' => $errors, 'request_body' => $request_body ] );
			return [
				'success'     => false,
				'error'       => $error,
				'message'     => $message,
				'errors'      => $errors,
				'status_code' => $status_code,
			];
		}

		// Handle server errors (500) - unexpected server-side errors.
		if ( $status_code === 500 ) {
			$error   = isset( $data['error'] ) ? $data['error'] : 'internal_error';
			$message = isset( $data['message'] ) ? $data['message'] : 'An internal error occurred while processing the compatibility check';

			$this->logger->error( "Compatibility check server error: {$error} - {$message} (Status: {$status_code})", [ 'response' => $data ] );
			return [
				'success'     => false,
				'error'       => $error,
				'message'     => $message,
				'status_code' => $status_code,
			];
		}

		// Handle success (200) - expected successful response.
		if ( $status_code === 200 ) {
			$this->logger->debug( "Compatibility check successful for plugin {$plugin_identifier} version {$plugin_version}" );

			// Return CompatibilityResponse object.
			return new CompatibilityResponse( $data );
		}

		// Handle unexpected status codes.
		$error   = isset( $data['error'] ) ? $data['error'] : 'unknown_error';
		$message = isset( $data['message'] ) ? $data['message'] : "Unexpected response status: {$status_code}";

		$this->logger->error( "Compatibility check unexpected response: {$message} (Status: {$status_code})", [ 'response' => $data ] );
		return [
			'success'     => false,
			'error'       => $error,
			'message'     => $message,
			'status_code' => $status_code,
		];
	}

	/**
	 * Generic POST wrapper for plugin-specific endpoints.
	 *
	 * @since 1.0.0
	 * @param string $route Full route path including version (e.g., 'api/v1/fmo/upload/init').
	 * @param array  $data  Request data.
	 * @return array Response array with 'success' and optional 'error' or data.
	 */
	public function post( $route, $data = [] ) {
		$endpoint_url = trailingslashit( $this->base_url ) . ltrim( $route, '/' );

		$response = wp_remote_post(
			$endpoint_url,
			[
				'timeout' => $this->timeout,
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $data ),
			]
		);

		return $this->handle_generic_response( $response, 'POST', $endpoint_url );
	}

	/**
	 * Generic GET wrapper for plugin-specific endpoints.
	 *
	 * @since 1.0.0
	 * @param string $route  Full route path including version (e.g., 'api/v1/fmo/upload/status').
	 * @param array  $params Query parameters.
	 * @return array Response array with 'success' and optional 'error' or data.
	 */
	public function get( $route, $params = [] ) {
		$endpoint_url = trailingslashit( $this->base_url ) . ltrim( $route, '/' );

		if ( ! empty( $params ) ) {
			$endpoint_url = add_query_arg( $params, $endpoint_url );
		}

		$response = wp_remote_get(
			$endpoint_url,
			[
				'timeout' => $this->timeout,
				'headers' => [
					'Content-Type' => 'application/json',
				],
			]
		);

		return $this->handle_generic_response( $response, 'GET', $endpoint_url );
	}

	/**
	 * Generic PUT wrapper for plugin-specific endpoints.
	 *
	 * @since 1.0.0
	 * @param string $route Full route path including version (e.g., 'api/v1/fmo/upload/update').
	 * @param array  $data Request data.
	 * @return array Response array with 'success' and optional 'error' or data.
	 */
	public function put( $route, $data = [] ) {
		$endpoint_url = trailingslashit( $this->base_url ) . ltrim( $route, '/' );

		$response = wp_remote_request(
			$endpoint_url,
			[
				'method'  => 'PUT',
				'timeout' => $this->timeout,
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $data ),
			]
		);

		return $this->handle_generic_response( $response, 'PUT', $endpoint_url );
	}

	/**
	 * Generic DELETE wrapper for plugin-specific endpoints.
	 *
	 * @since 1.0.0
	 * @param string $route  Full route path including version (e.g., 'api/v1/fmo/upload/delete').
	 * @param array  $params Query parameters.
	 * @return array Response array with 'success' and optional 'error' or data.
	 */
	public function delete( $route, $params = [] ) {
		$endpoint_url = trailingslashit( $this->base_url ) . ltrim( $route, '/' );

		if ( ! empty( $params ) ) {
			$endpoint_url = add_query_arg( $params, $endpoint_url );
		}

		$response = wp_remote_request(
			$endpoint_url,
			[
				'method'  => 'DELETE',
				'timeout' => $this->timeout,
				'headers' => [
					'Content-Type' => 'application/json',
				],
			]
		);

		return $this->handle_generic_response( $response, 'DELETE', $endpoint_url );
	}

	/**
	 * Handle license API response.
	 *
	 * @since 1.0.0
	 * @param array|\WP_Error $response WordPress HTTP response.
	 * @param string          $operation Operation type ('activation' or 'validation').
	 * @param string          $account_id Account ID for logging.
	 * @return array Response array.
	 */
	private function handle_license_response( $response, $operation, $account_id ) {
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$log_level     = ( $operation === 'activation' ) ? 'error' : 'debug';
			$this->logger->log( $log_level, "License {$operation} network error: {$error_message}" );
			return [
				'success'     => false,
				'error'       => 'network_error',
				'message'     => $error_message,
				'status_code' => null,
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		// Handle validation errors (422) - unexpected, indicates a bug in our request.
		if ( $status_code === 422 ) {
			$error   = isset( $data['error'] ) ? $data['error'] : 'validation_failed';
			$message = isset( $data['message'] ) ? $data['message'] : 'Invalid request parameters';
			$errors  = isset( $data['errors'] ) ? $data['errors'] : [];

			$this->logger->error( "License {$operation} validation failed: {$message}", [ 'errors' => $errors ] );
			return [
				'success'     => false,
				'error'       => $error,
				'message'     => $message,
				'errors'      => $errors,
				'status_code' => $status_code,
			];
		}

		// Handle license errors (403) - expected business logic errors (invalid, expired, inactive license).
		if ( $status_code === 403 ) {
			$error   = isset( $data['error'] ) ? $data['error'] : 'license_invalid';
			$message = isset( $data['message'] ) ? $data['message'] : "License {$operation} failed";

			// These are expected business logic responses, log as debug.
			$this->logger->debug( "License {$operation} rejected: {$error} - {$message}" );
			return [
				'success'     => false,
				'error'       => $error,
				'message'     => $message,
				'status_code' => $status_code,
			];
		}

		// Handle client errors (400) - check error type to determine if expected or unexpected.
		if ( $status_code === 400 ) {
			$error   = isset( $data['error'] ) ? $data['error'] : ( ( $operation === 'activation' ) ? 'activation_failed' : 'validation_failed' );
			$message = isset( $data['message'] ) ? $data['message'] : "License {$operation} failed";

			// Some 400 errors are expected business logic, others are unexpected (auth errors, etc.).
			$expected_errors = ( $operation === 'activation' ) ? [ 'activation_failed' ] : [ 'validation_failed' ];
			if ( in_array( $error, $expected_errors, true ) ) {
				$this->logger->debug( "License {$operation} failed: {$error} - {$message}" );
			} else {
				$this->logger->error( "License {$operation} unexpected error: {$error} - {$message} (Status: {$status_code})", [ 'response' => $data ] );
			}

			return [
				'success'     => false,
				'error'       => $error,
				'message'     => $message,
				'status_code' => $status_code,
			];
		}

		// Handle server errors (500) - unexpected server-side errors.
		if ( $status_code === 500 ) {
			$error   = isset( $data['error'] ) ? $data['error'] : 'internal_error';
			$message = isset( $data['message'] ) ? $data['message'] : "An internal error occurred while processing the {$operation} request";

			$this->logger->error( "License {$operation} server error: {$error} - {$message} (Status: {$status_code})", [ 'response' => $data ] );
			return [
				'success'     => false,
				'error'       => $error,
				'message'     => $message,
				'status_code' => $status_code,
			];
		}

		// Handle success (200) - expected successful response.
		if ( $status_code === 200 ) {
			$valid = isset( $data['valid'] ) ? (bool) $data['valid'] : false;
			// Success is expected, log as debug.
			$this->logger->debug( "License {$operation} successful for account " . AccountIdService::get_instance()->obfuscate_account_id() . ", valid: " . ( $valid ? 'true' : 'false' ) );

			return [
				'success' => true,
				'valid'   => $valid,
				'message' => isset( $data['message'] ) ? $data['message'] : "License {$operation} successfully",
			];
		}

		// Handle unexpected status codes - these indicate a problem.
		$error   = isset( $data['error'] ) ? $data['error'] : 'unknown_error';
		$message = isset( $data['message'] ) ? $data['message'] : "Unexpected response status: {$status_code}";

		// In debug mode, include full response in message for troubleshooting.
		$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		if ( $is_debug ) {
			// If we successfully decoded JSON, use that, otherwise use raw body.
			if ( $data !== null && is_array( $data ) ) {
				$response_json = wp_json_encode( $data, JSON_PRETTY_PRINT );
				$message       = sprintf( '%s. Response: %s', $message, esc_html( $response_json ) );
			} else {
				// Fallback to raw response body, escaped for frontend.
				$message = sprintf( '%s. Response: %s', $message, esc_html( $body ) );
			}
		}

		$this->logger->error( "License {$operation} unexpected response: {$message} (Status: {$status_code})", [ 'response' => $data ] );
		return [
			'success'     => false,
			'error'       => $error,
			'message'     => $message,
			'status_code' => $status_code,
		];
	}

	/**
	 * Handle generic API response.
	 *
	 * @since 1.0.0
	 * @param array|\WP_Error $response WordPress HTTP response.
	 * @param string          $method   HTTP method.
	 * @param string          $endpoint Endpoint URL.
	 * @return array Response array.
	 */
	private function handle_generic_response( $response, $method, $endpoint ) {
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->logger->error( "{$method} request to {$endpoint} failed: {$error_message}" );
			return [
				'success' => false,
				'error'   => 'network_error',
				'message' => $error_message,
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code !== 200 ) {
			$error = isset( $data['error'] ) ? $data['error'] : 'Unknown error from external service';
			$this->logger->error( "{$method} request to {$endpoint} returned error: {$error} (Status: {$status_code})" );
			return [
				'success'     => false,
				'error'       => $error,
				'message'     => isset( $data['message'] ) ? $data['message'] : $error,
				'status_code' => $status_code,
			];
		}

		// Success - return data.
		return [
			'success' => true,
			'data'    => $data,
		];
	}

	/**
	 * Check compatibility before making external API request.
	 *
	 * Ensures we have fresh compatibility data before operations. Uses cache if valid,
	 * otherwise fetches from API and caches the result. Then checks if operations should be blocked.
	 *
	 * This method is called automatically by activate_license() and validate_license() methods.
	 * Plugin-specific endpoints should call this manually if they need compatibility checking.
	 *
	 * @since 1.0.0
	 * @return bool True if compatible and can proceed, false if blocked.
	 */
	private function check_compatibility_before_request() {
		// Get compatibility validator instance from CompatibilityService.
		// No plugin slug needed - uses the current plugin from FluxPlugins::init().
		$validator = CompatibilityService::get_validator();

		if ( $validator === null ) {
			// Validator not initialized yet, allow operations (fail open).
			return true;
		}

		// Ensure we have fresh compatibility data (uses cache if valid, fetches if needed).
		// This ensures we have up-to-date compatibility info before operations.
		$validator->check_compatibility();

		// Check if operations should be blocked using the cached/fresh data.
		if ( $validator->should_block_operations() ) {
			return false;
		}

		return true;
	}

	/**
	 * Update license validation notice transient based on API result.
	 *
	 * This method is called after license validation or activation API calls
	 * to update the transient that controls whether the admin notice should be displayed.
	 *
	 * - If license is valid (success=true and valid=true): Clear transient to hide notice
	 * - If license is invalid (any failure or valid=false): Set transient to show notice
	 *
	 * This is the single source of truth for license validation status from API calls.
	 *
	 * @since 1.0.0
	 * @param array $result API response result array with 'success' and optionally 'valid' keys.
	 * @return void
	 */
	private function update_license_notice_transient( $result ) {
		$license_service = LicenseService::get_instance();

		// Determine if license is valid based on API result.
		$is_valid = false;
		if ( isset( $result['success'] ) && $result['success'] === true ) {
			// For validation, check 'valid' key. For activation, success means valid.
			$is_valid = isset( $result['valid'] ) ? (bool) $result['valid'] : true;
		}

		// Update notice transient based on API result.
		// This updates the transient directly based on the API result, independent
		// of the current license_last_valid_date state (which may be updated by the controller).
		$license_service->update_notice_transient_from_api_result( $is_valid );
	}
}

