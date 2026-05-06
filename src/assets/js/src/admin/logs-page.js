/**
 * Logs page entry point.
 *
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * This file bootstraps the React Logs page component.
 * Plugins should build this as a separate bundle and enqueue it for the logs page.
 *
 * @package FluxPlugins\Common
 * @since 1.0.0
 * @since 1.0.0 Added externally managed source notice.
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import LogsPage from '../components/Logs/LogsPage';

// Initialize React app when DOM is ready
(function() {
  function initLogsApp() {
    const container = document.getElementById('flux-plugins-common-logs-app');
    
    if (!container) {
      console.error('Flux Plugins Common: Logs app container not found');
      return;
    }

    // Create React root and render Logs page
    try {
      const root = createRoot(container);
      root.render(React.createElement(LogsPage));
    } catch (error) {
      console.error('Flux Plugins Common: Failed to render Logs page', error);
      container.innerHTML = '<div class="notice notice-error"><p>Failed to load logs page. Please ensure all dependencies are loaded.</p></div>';
    }
  }

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLogsApp);
  } else {
    initLogsApp();
  }
})();

