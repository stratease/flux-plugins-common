/**
 * License page entry point.
 *
 * This file bootstraps the React License page component.
 * Plugins should build this as a separate bundle and enqueue it for the license page.
 *
 * @package FluxPlugins\Common
 * @since 1.0.0
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import LicensePage from '../components/License/LicensePage';

// Initialize React app when DOM is ready
(function() {
  function initLicenseApp() {
    const container = document.getElementById('flux-plugins-common-license-app');
    
    if (!container) {
      console.error('Flux Plugins Common: License app container not found');
      return;
    }

    // Create React root and render License page
    try {
      const root = createRoot(container);
      root.render(React.createElement(LicensePage));
    } catch (error) {
      console.error('Flux Plugins Common: Failed to render License page', error);
      container.innerHTML = '<div class="notice notice-error"><p>Failed to load license page. Please ensure all dependencies are loaded.</p></div>';
    }
  }

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLicenseApp);
  } else {
    initLicenseApp();
  }
})();

