import { createTheme } from '@mui/material/styles';

/**
 * Flux Plugins Common theme configuration
 * 
 * Shared theme for all Flux Plugins, ensuring consistent styling across the suite.
 * 
 * @since 1.0.0
 */
const theme = createTheme({
  palette: {
    mode: 'light',
    primary: {
      main: '#1976d2',
      light: '#42a5f5',
      dark: '#1565c0',
      contrastText: '#ffffff',
    },
    secondary: {
      main: '#dc004e',
      light: '#ff5983',
      dark: '#9a0036',
      contrastText: '#ffffff',
    },
    background: {
      default: '#f5f5f5',
      paper: '#ffffff',
    },
    text: {
      primary: '#212121',
      secondary: '#757575',
    },
    divider: '#e0e0e0',
    grey: {
      50: '#fafafa',
      100: '#f5f5f5',
      200: '#eeeeee',
      300: '#e0e0e0',
      400: '#bdbdbd',
      500: '#9e9e9e',
      600: '#757575',
      700: '#616161',
      800: '#424242',
      900: '#212121',
    },
  },
  typography: {
    fontFamily: [
      '-apple-system',
      'BlinkMacSystemFont',
      '"Segoe UI"',
      'Roboto',
      '"Helvetica Neue"',
      'Arial',
      'sans-serif',
    ].join(','),
    // @since 1.0.0 Use px values to prevent rem/em conflicts with WordPress admin styles
    h1: {
      fontSize: '40px', // 2.5rem
      fontWeight: 600,
      lineHeight: 1.2,
    },
    h2: {
      fontSize: '32px', // 2rem
      fontWeight: 600,
      lineHeight: 1.3,
    },
    h3: {
      fontSize: '28px', // 1.75rem
      fontWeight: 600,
      lineHeight: 1.3,
    },
    h4: {
      fontSize: '24px', // 1.5rem
      fontWeight: 600,
      lineHeight: 1.4,
    },
    h5: {
      fontSize: '20px', // 1.25rem
      fontWeight: 600,
      lineHeight: 1.4,
    },
    h6: {
      fontSize: '16px', // 1rem
      fontWeight: 600,
      lineHeight: 1.5,
    },
    body1: {
      fontSize: '16px', // 1rem
      lineHeight: 1.5,
    },
    body2: {
      fontSize: '14px', // 0.875rem
      lineHeight: 1.43,
    },
    caption: {
      fontSize: '12px', // 0.75rem
      lineHeight: 1.66,
    },
  },
  components: {
    MuiPaper: {
      defaultProps: {
        elevation: 1,
      },
      styleOverrides: {
        root: {
          backgroundColor: '#ffffff',
          borderRadius: 8,
        },
      },
    },
    MuiCard: {
      styleOverrides: {
        root: {
          backgroundColor: '#ffffff',
          borderRadius: 8,
          boxShadow: '0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24)',
        },
      },
    },
    MuiButton: {
      styleOverrides: {
        root: {
          borderRadius: 6,
          textTransform: 'none',
          fontWeight: 500,
        },
      },
    },
    MuiChip: {
      styleOverrides: {
        root: {
          borderRadius: 16,
        },
      },
    },
    MuiTabs: {
      styleOverrides: {
        root: {
          borderBottom: '1px solid #e0e0e0',
        },
        indicator: {
          height: 3,
          borderRadius: '3px 3px 0 0',
        },
      },
    },
    MuiTab: {
      styleOverrides: {
        root: {
          textTransform: 'none',
          fontWeight: 500,
          minHeight: 48,
        },
      },
    },
    // @since 1.0.0 Added to fix WordPress admin style conflicts
    MuiTypography: {
      styleOverrides: {
        root: {
          // Ensure typography uses theme defaults, not WordPress admin styles
          fontSize: 'inherit',
          lineHeight: 'inherit',
          fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        },
      },
    },
    // @since 1.0.0 Added to fix WordPress admin style conflicts
    MuiTextField: {
      styleOverrides: {
        root: {
          fontSize: '16px', // Use px to avoid rem/em conflicts
          // Reset WordPress form styles
          '& input, & textarea': {
            border: 'none',
            background: 'transparent',
            boxShadow: 'none',
            outline: 'none',
            fontSize: '16px',
            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
          },
        },
      },
    },
    // @since 1.0.0 Added to fix WordPress admin style conflicts
    MuiInputBase: {
      styleOverrides: {
        root: {
          fontSize: '16px', // Use px to avoid rem/em conflicts
          fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        },
        input: {
          fontSize: '16px', // Use px to avoid rem/em conflicts
          lineHeight: '1.5 !important',
          height: 'auto',
          padding: '16.5px 14px',
          boxSizing: 'border-box',
          fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
          '&::placeholder': {
            fontSize: '16px', // Use px to avoid rem/em conflicts
            opacity: 0.42,
            transition: 'opacity 200ms cubic-bezier(0.4, 0, 0.2, 1) 0ms',
          },
          '&:focus::placeholder': {
            opacity: 0,
          },
        },
      },
    },
    // @since 1.0.0 Added to fix WordPress admin style conflicts
    MuiOutlinedInput: {
      styleOverrides: {
        root: {
          borderRadius: 4,
        },
        notchedOutline: {
          borderWidth: '1px',
          borderColor: 'rgba(0, 0, 0, 0.23)',
        },
      },
    },
    // @since 1.0.0 Added to fix WordPress admin style conflicts
    MuiFormHelperText: {
      styleOverrides: {
        root: {
          fontSize: '12px', // Use px to avoid rem/em conflicts
          lineHeight: 1.66,
          margin: '3px 14px 0 14px',
        },
      },
    },
  },
  spacing: 8,
  shape: {
    borderRadius: 8,
  },
});

export default theme;

