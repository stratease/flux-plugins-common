/**
 * License API service for Flux Plugins Common
 * Works with the shared license REST API endpoints
 */

import apiFetch from '@wordpress/api-fetch';

class LicenseApiService {
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
      
      // Legacy format: return data directly
      return response;
    } catch (error) {
      // Throw the error for React Query to handle
      throw error;
    }
  }

  /**
   * Get license information
   * @returns {Promise} License data
   */
  async getLicense() {
    return this.request(`/${this.namespace}/license`);
  }

  /**
   * Activate license key
   * @param {string} licenseKey - License key to activate
   * @returns {Promise} Activation result
   */
  async activateLicense(licenseKey) {
    return this.request(`/${this.namespace}/license/activate`, {
      method: 'POST',
      body: JSON.stringify({ license_key: licenseKey }),
    });
  }

  /**
   * Validate current license key
   * @returns {Promise} Validation result
   */
  async validateLicense() {
    return this.request(`/${this.namespace}/license/validate`, {
      method: 'POST',
    });
  }

  /**
   * Get account ID
   * @returns {Promise} Account ID data
   */
  async getAccountId() {
    return this.request(`/${this.namespace}/account-id`);
  }
}

// Export singleton instance
export const licenseApiService = new LicenseApiService();
export default licenseApiService;

