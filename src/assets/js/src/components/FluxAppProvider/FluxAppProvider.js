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

          /* Override WordPress admin styles that affect MUI components */
          /* Focus on only the explicitly problematic areas */
          
          /* Typography fixes - WordPress admin often sets small font-sizes */
          /* Use px values to avoid rem/em calculation conflicts with WordPress */
          /* Note: Do not set margin here - it would override component sx/style margins */
          .MuiTypography-root {
            font-size: inherit !important;
            line-height: inherit !important;
          }
          
          .MuiTypography-h1 {
            font-size: 40px !important;
            font-weight: 600 !important;
            line-height: 1.2 !important;
          }
          
          .MuiTypography-h2 {
            font-size: 32px !important;
            font-weight: 600 !important;
            line-height: 1.3 !important;
          }
          
          .MuiTypography-h3 {
            font-size: 28px !important;
            font-weight: 600 !important;
            line-height: 1.3 !important;
          }
          
          .MuiTypography-h4 {
            font-size: 24px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
          }
          
          .MuiTypography-h5 {
            font-size: 20px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
          }
          
          .MuiTypography-h6 {
            font-size: 16px !important;
            font-weight: 600 !important;
            line-height: 1.5 !important;
          }
          
          .MuiTypography-body1 {
            font-size: 16px !important;
            line-height: 1.5 !important;
          }
          
          .MuiTypography-body2 {
            font-size: 14px !important;
            line-height: 1.43 !important;
          }
          
          .MuiTypography-caption {
            font-size: 12px !important;
            line-height: 1.66 !important;
          }

          /* TextField and Input fixes - fix border and placeholder spacing issues */
          .MuiInputBase-input {
            font-size: 16px !important;
            padding: 16.5px 14px !important;
          }
          
          .MuiInputBase-input::placeholder {
            font-size: 16px !important;
            opacity: 0.42 !important;
          }

          /* Outlined Input border fixes */
          .MuiOutlinedInput-notchedOutline {
            border-width: 1px !important;
            border-color: rgba(0, 0, 0, 0.23) !important;
          }
          
          .MuiOutlinedInput-root:hover .MuiOutlinedInput-notchedOutline {
            border-color: rgba(0, 0, 0, 0.87) !important;
          }
          
          .MuiOutlinedInput-root.Mui-focused .MuiOutlinedInput-notchedOutline {
            border-width: 2px !important;
          }

          /* Override WordPress form styles that affect MUI inputs */
          .MuiTextField-root input,
          .MuiTextField-root textarea,
          .MuiInputBase-input {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            outline: none !important;
          }
        `}
      />
      {children}
    </ThemeProvider>
  );
};

export default FluxAppProvider;

