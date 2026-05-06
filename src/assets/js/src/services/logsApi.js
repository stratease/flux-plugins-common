/**
 * Logs API service for Flux Plugins Common
 * Works with the shared logs REST API endpoints
 *
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * @since 1.0.0 Added externally managed source notice.
 */

import apiFetch from '@wordpress/api-fetch';

class LogsApiService {
  constructor() {
    this.namespace = 'flux-plugins-common/v1';
    
    // Configure apiFetch with proper API root
    const apiRoot = window.fluxPluginsCommon?.apiUrl || '/wp-json/';
    apiFetch.use(apiFetch.createRootURLMiddleware(apiRoot));
  }

  /**
   * Make a request using WordPress apiFetch
   * @param {string} endpoint - The API endpoint
   * @param {Object} options - Request options
   * @returns {Promise} - API response
   */
  async request(endpoint, options = {}) {
    const defaultOptions = {
      path: endpoint,
      method: 'GET',
      headers: {
        'X-WP-Nonce': window.fluxPluginsCommon?.nonce || '',
        'Content-Type': 'application/json',
      },
    };

    const mergedOptions = {
      ...defaultOptions,
      ...options,
      headers: {
        ...defaultOptions.headers,
        ...(options.headers || {}),
      },
    };

    try {
      const response = await apiFetch(mergedOptions);
      
      // Handle structured response format
      if (response && typeof response === 'object' && response.success !== undefined) {
        return response.data;
      }
      
      return response;
    } catch (error) {
      throw error;
    }
  }

  /**
   * Get logs
   * @param {Object} params - Query parameters
   * @returns {Promise} Logs data
   */
  async getLogs(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    return this.request(`/${this.namespace}/logs?${queryString}`);
  }

  /**
   * Get options (for logging setting)
   * @returns {Promise} Options data
   */
  async getOptions() {
    // Get from site option
    const options = window.fluxPluginsCommon?.options || {};
    return { enable_logging: options.enable_logging !== false };
  }
}

// Export singleton instance
export const logsApiService = new LogsApiService();
export default logsApiService;

