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
          /* Use high specificity to override WordPress admin CSS */
          
          /* Typography fixes - WordPress admin often sets small font-sizes */
          /* Use px values to avoid rem/em calculation conflicts with WordPress */
          .MuiTypography-root {
            font-size: inherit !important;
            line-height: inherit !important;
            margin: 0 !important;
            /* Reset any WordPress typography overrides */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
          }
          
          .MuiTypography-h1 {
            font-size: 40px !important; /* 2.5rem */
            font-weight: 600 !important;
            line-height: 1.2 !important;
          }
          
          .MuiTypography-h2 {
            font-size: 32px !important; /* 2rem */
            font-weight: 600 !important;
            line-height: 1.3 !important;
          }
          
          .MuiTypography-h3 {
            font-size: 28px !important; /* 1.75rem */
            font-weight: 600 !important;
            line-height: 1.3 !important;
          }
          
          .MuiTypography-h4 {
            font-size: 24px !important; /* 1.5rem */
            font-weight: 600 !important;
            line-height: 1.4 !important;
          }
          
          .MuiTypography-h5 {
            font-size: 20px !important; /* 1.25rem */
            font-weight: 600 !important;
            line-height: 1.4 !important;
          }
          
          .MuiTypography-h6 {
            font-size: 16px !important; /* 1rem */
            font-weight: 600 !important;
            line-height: 1.5 !important;
          }
          
          .MuiTypography-body1 {
            font-size: 16px !important; /* 1rem */
            line-height: 1.5 !important;
          }
          
          .MuiTypography-body2 {
            font-size: 14px !important; /* 0.875rem */
            line-height: 1.43 !important;
          }
          
          .MuiTypography-caption {
            font-size: 12px !important; /* 0.75rem */
            line-height: 1.66 !important;
          }

          /* TextField and Input fixes */
          /* Use px values to avoid rem/em conflicts */
          .MuiTextField-root,
          .MuiInputBase-root {
            font-size: 16px !important; /* 1rem */
          }
          
          .MuiInputBase-input {
            font-size: 16px !important; /* 1rem */
            line-height: 1.5 !important;
            padding: 16.5px 14px !important;
            box-sizing: border-box !important;
            /* Reset WordPress input styles */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
          }
          
          .MuiInputBase-input::placeholder {
            font-size: 16px !important; /* 1rem */
            opacity: 0.42 !important;
            transition: opacity 200ms cubic-bezier(0.4, 0, 0.2, 1) 0ms !important;
          }
          
          .MuiInputBase-input:focus::placeholder {
            opacity: 0 !important;
          }

          /* Outlined Input border fixes */
          .MuiOutlinedInput-root {
            border-radius: 4px !important;
          }
          
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

          /* Button fixes - override WordPress button styles */
          .MuiButton-root {
            font-size: 14px !important; /* 0.875rem */
            font-weight: 500 !important;
            line-height: 1.75 !important;
            letter-spacing: 0.02857em !important;
            padding: 6px 16px !important;
            min-width: 64px !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
          }

          /* FormHelperText fixes */
          .MuiFormHelperText-root {
            font-size: 12px !important; /* 0.75rem */
            line-height: 1.66 !important;
            margin: 3px 14px 0 14px !important;
          }

          /* Alert fixes */
          .MuiAlert-root {
            font-size: 14px !important; /* 0.875rem */
            line-height: 1.43 !important;
          }
          
          /* Override WordPress admin styles for all form elements inside MUI */
          .MuiTextField-root input[type="text"],
          .MuiTextField-root input[type="password"],
          .MuiTextField-root input[type="email"],
          .MuiTextField-root input[type="number"],
          .MuiTextField-root textarea {
            font-size: 16px !important;
          }

          /* Box and Container spacing resets */
          .MuiBox-root,
          .MuiContainer-root {
            box-sizing: border-box !important;
          }

          /* Stack spacing fixes */
          .MuiStack-root {
            display: flex !important;
            box-sizing: border-box !important;
          }

          /* Override WordPress form styles that might affect MUI */
          .MuiTextField-root input,
          .MuiTextField-root textarea,
          .MuiInputBase-input {
            /* Reset WordPress input styles */
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            outline: none !important;
          }
          
          /* Ensure MUI components are not affected by WordPress button styles */
          .MuiButton-root,
          .MuiIconButton-root {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
          }
          
          .MuiButton-contained {
            box-shadow: 0px 3px 1px -2px rgba(0,0,0,0.2), 0px 2px 2px 0px rgba(0,0,0,0.14), 0px 1px 5px 0px rgba(0,0,0,0.12) !important;
          }

          /* Reset WordPress form element styles that affect MUI */
          .MuiInputBase-root input[type="text"],
          .MuiInputBase-root input[type="password"],
          .MuiInputBase-root input[type="email"],
          .MuiInputBase-root input[type="number"],
          .MuiInputBase-root textarea {
            /* Reset WordPress admin input styles */
            width: 100% !important;
            border: none !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            outline: none !important;
            padding: 0 !important;
            margin: 0 !important;
            font-size: 1rem !important;
            font-family: inherit !important;
            line-height: 1.5 !important;
          }

          /* Ensure proper font rendering */
          .MuiTypography-root,
          .MuiInputBase-root,
          .MuiButton-root {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            font-feature-settings: 'liga';
          }

          /* Override WordPress table styles that might affect MUI */
          .MuiTable-root,
          .MuiTableContainer-root {
            font-size: 0.875rem !important;
          }

          /* Ensure proper box-sizing */
          .MuiBox-root *,
          .MuiContainer-root *,
          .MuiStack-root * {
            box-sizing: border-box !important;
          }
        `}
      />
      {children}
    </ThemeProvider>
  );
};

export default FluxAppProvider;

