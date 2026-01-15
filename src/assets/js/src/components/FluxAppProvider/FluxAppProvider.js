import React from 'react';
import { ThemeProvider } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { Global, css } from '@emotion/react';
import theme from '../../theme';

/**
 * Flux App Provider - Provides theme and baseline styles for all Flux Plugins
 * 
 * Uses the shared Flux Plugins theme for consistent styling across all plugins.
 * Includes WordPress admin style overrides to prevent conflicts with Material UI.
 * 
 * @since 1.0.0
 * @param {Object} props - Component props
 * @param {React.ReactNode} props.children - App content
 * @returns {JSX.Element} FluxAppProvider component
 */
const FluxAppProvider = ({ children }) => {
  return (
    <ThemeProvider theme={theme}>
      <CssBaseline />
      <Global
        styles={css`
          /* Checkbox input override */
          .MuiCheckbox-root input[type="checkbox"] {
            opacity: 0 !important;
            position: absolute !important;
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            cursor: pointer !important;
            z-index: 1 !important;
          }

        `}
      />
      {children}
    </ThemeProvider>
  );
};

export default FluxAppProvider;

