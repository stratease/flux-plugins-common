import React from 'react';
import { ThemeProvider } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import theme from '../../theme';

/**
 * Flux App Provider - Provides theme and baseline styles for all Flux Plugins
 * 
 * Uses the shared Flux Plugins theme for consistent styling across all plugins.
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
      {children}
    </ThemeProvider>
  );
};

export default FluxAppProvider;

