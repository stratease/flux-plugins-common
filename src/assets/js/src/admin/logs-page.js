/**
 * Logs page entry point.
 *
 * This file bootstraps the React Logs page component.
 * Plugins should build this as a separate bundle and enqueue it for the logs page.
 *
 * @package FluxPlugins\Common
 * @since 1.0.0
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import LogsPage from '../components/Logs/LogsPage';
import { FluxAppProvider } from '../components/FluxAppProvider';

// Create a client for this page
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

// Initialize React app when DOM is ready
(function() {
  function initLogsApp() {
    console.log('Flux Plugins Common: Initializing logs app...');
    const container = document.getElementById('flux-plugins-common-logs-app');
    
    if (!container) {
      console.error('Flux Plugins Common: Logs app container not found. Available IDs:', 
        Array.from(document.querySelectorAll('[id]')).map(el => el.id).join(', '));
      return;
    }

    console.log('Flux Plugins Common: Container found, creating React root...');

    // Create React root and render Logs page
    try {
      const root = createRoot(container);
      console.log('Flux Plugins Common: React root created, rendering LogsPage...');
      root.render(
        React.createElement(QueryClientProvider, { client: queryClient },
          React.createElement(FluxAppProvider,
            React.createElement(LogsPage)
          )
        )
      );
      console.log('Flux Plugins Common: LogsPage rendered successfully');
    } catch (error) {
      console.error('Flux Plugins Common: Failed to render Logs page', error);
      container.innerHTML = '<div class="notice notice-error"><p>Failed to load logs page: ' + error.message + '</p></div>';
    }
  }

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLogsApp);
  } else {
    initLogsApp();
  }
})();

