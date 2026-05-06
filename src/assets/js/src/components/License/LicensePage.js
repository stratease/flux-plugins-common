/**
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * @since 1.0.0 Added externally managed source notice.
 */

import React, { useState, useEffect, useRef, useCallback } from 'react';
import {
  Typography,
  Box,
  Grid,
  TextField,
  Stack,
  Alert,
  Button,
  CircularProgress,
  InputAdornment,
  Tooltip,
  IconButton,
  Link,
  Card,
  CardContent,
  Divider,
} from '@mui/material';
import { CheckCircle, Error as ErrorIcon, Refresh, Star, Visibility, VisibilityOff, ContentCopy, Check } from '@mui/icons-material';
import { __ } from '@wordpress/i18n';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useLicense, useActivateLicense, useValidateLicense, useAccountId } from '../../hooks/useLicense';
import { PageLayout } from '../PageLayout';
import { FluxAppProvider } from '../FluxAppProvider';
import copy from 'clipboard-copy';

// Create a client for this page
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

/**
 * License page component
 * Shared across all Flux Plugins
 */
const LicensePageContent = () => {
  const [localLicenseKey, setLocalLicenseKey] = useState('');
  const [licenseActivationError, setLicenseActivationError] = useState(null);
  const [isLicenseInitialized, setIsLicenseInitialized] = useState(false);
  const [showAccountId, setShowAccountId] = useState(false);
  const [copiedAccountId, setCopiedAccountId] = useState(false);
  
  // React Query hooks for data fetching
  const { data: licenseData, isLoading: licenseLoading, error: licenseError } = useLicense();
  const { data: accountIdData, isLoading: accountIdLoading } = useAccountId();
  const activateLicenseMutation = useActivateLicense();
  const validateLicenseMutation = useValidateLicense();

  // Debounce timer for license activation
  const debounceTimerRef = useRef(null);

  // Initialize license key ONCE from license data on first load only
  useEffect(() => {
    if (!isLicenseInitialized && licenseData && typeof licenseData === 'object') {
      if (licenseData.license_key !== undefined) {
        setLocalLicenseKey(licenseData.license_key || '');
        setIsLicenseInitialized(true);
      }
    }
  }, [licenseData, isLicenseInitialized]);

  // Debounced license activation function
  const debouncedActivateLicense = useCallback((key) => {
    // Clear any existing timer
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }
    
    // Set new timer
    debounceTimerRef.current = setTimeout(() => {
      const trimmedKey = key.trim();
      // Activate if key is not empty
      if (trimmedKey) {
        activateLicenseMutation.mutate(trimmedKey);
      }
    }, 1000); // 1 second debounce
  }, [activateLicenseMutation]);

  // Cleanup debounce timer on unmount
  useEffect(() => {
    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    };
  }, []);
  
  // Monitor license activation mutation for errors
  useEffect(() => {
    if (activateLicenseMutation.isError) {
      const error = activateLicenseMutation.error;
      const errorData = error?.data || {};
      const errorCode = errorData.error_code || error?.code || 'unknown_error';
      const errorMessage = errorData.message || error?.message || __('License activation failed', 'flux-plugins-common');
      
      setLicenseActivationError({
        success: false,
        error: errorCode,
        message: errorMessage,
      });
    } else if (activateLicenseMutation.isSuccess) {
      // Clear error on success
      setLicenseActivationError(null);
    }
  }, [activateLicenseMutation.isError, activateLicenseMutation.isSuccess, activateLicenseMutation.error]);

  const handleLicenseKeyChange = (event) => {
    const newLicenseKey = event.target.value;
    
    // Update local state immediately for instant feedback
    setLocalLicenseKey(newLicenseKey);
    
    // Clear any previous activation errors
    setLicenseActivationError(null);
    
    // Trigger debounced activation on change
    if (newLicenseKey.trim()) {
      debouncedActivateLicense(newLicenseKey);
    } else {
      // Clear debounce timer if field is empty
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    }
  };

  const handleRevalidateLicense = async () => {
    if (!localLicenseKey) {
      return;
    }
    
    // First activate the license to ensure it's activated
    try {
      await activateLicenseMutation.mutateAsync(localLicenseKey);
      // After activation succeeds, validate the license
      await validateLicenseMutation.mutateAsync();
    } catch (error) {
      // Error handling is done by the mutation hooks
      // If activation fails, we still try to validate
      await validateLicenseMutation.mutateAsync();
    }
  };

  // Format date for display using WordPress date formatting
  const formatLicenseDate = (dateString) => {
    if (!dateString) {
      return null;
    }
    
    try {
      // Use WordPress date formatting if available
      if (window.wp?.date?.dateI18n) {
        // WordPress date format from settings
        const dateFormat = window.wp?.date?.settings?.formats?.date || 'F j, Y';
        const timeFormat = window.wp?.date?.settings?.formats?.time || 'g:i a';
        const format = `${dateFormat} ${timeFormat}`;
        
        // Parse the GMT date and format it
        const date = new Date(dateString + ' UTC');
        return window.wp.date.dateI18n(format, date);
      } else {
        // Fallback to JavaScript date formatting
        const date = new Date(dateString + ' UTC');
        return date.toLocaleString();
      }
    } catch (e) {
      // Fallback to simple date string
      return dateString;
    }
  };

  const isLoading = licenseLoading;
  const licenseKey = localLicenseKey;
  const accountId = accountIdData?.account_id || '';

  // Handle copy account ID to clipboard
  const handleCopyAccountId = async () => {
    if (!accountId) {
      return;
    }
    
    try {
      await copy(accountId);
      setCopiedAccountId(true);
      setTimeout(() => {
        setCopiedAccountId(false);
      }, 2000);
    } catch (err) {
      // Copy failed - could show an error message to user
      console.error('Failed to copy account ID:', err);
    }
  };

  return (
    <PageLayout title={__('Flux Suite - License', 'flux-plugins-common')}>
      <Grid container spacing={3}>
        {/* First Column: License state messages and License field */}
        <Grid item xs={12} md={6}>
          <Stack spacing={3}>
            {/* Introduction message */}
            {licenseKey && licenseData?.license_is_valid ? (
              <Typography variant="body1" color="text.secondary">
                {__('Your license provides access to download and use all plugins in the Flux Suite.', 'flux-plugins-common')}{' '}
                <Link
                  href="https://fluxplugins.com"
                  target="_blank"
                  rel="noopener noreferrer"
                  sx={{ textDecoration: 'none', fontWeight: 500 }}
                >
                  {__('Browse plugins', 'flux-plugins-common')}
                </Link>
              </Typography>
            ) : (
              <Typography variant="body1" color="text.secondary">
                {__('Enter your Flux Suite license key to unlock premium features across all Flux Suite plugins.', 'flux-plugins-common')}
              </Typography>
            )}

            {/* Error alerts */}
            {licenseError && (
              <Alert severity="error">
                {licenseError.message || __('Failed to load license information', 'flux-plugins-common')}
              </Alert>
            )}

            {licenseActivationError && (
              <Alert 
                severity="error" 
                onClose={() => setLicenseActivationError(null)}
              >
                <Typography variant="body2" component="div">
                  <strong>{__('License Activation Failed', 'flux-plugins-common')}</strong>
                  <Typography variant="body2" component="div" sx={{ mt: 0.5 }}>
                    {licenseActivationError.message || __('An error occurred while activating your license. Please check your license key and try again.', 'flux-plugins-common')}
                  </Typography>
                </Typography>
              </Alert>
            )}

            {/* License field and status */}
            <Stack spacing={2}>
              <TextField
                fullWidth
                label={__('License Key', 'flux-plugins-common')}
                placeholder={__('Enter your license key', 'flux-plugins-common')}
                value={licenseKey}
                disabled={isLoading || activateLicenseMutation.isPending}
                onChange={handleLicenseKeyChange}
                variant="outlined"
                InputProps={{
                  endAdornment: (
                    <InputAdornment position="end">
                      <Stack direction="row" spacing={0.5} alignItems="center">
                        {activateLicenseMutation.isPending || validateLicenseMutation.isPending ? (
                          <CircularProgress size={20} />
                        ) : licenseKey && licenseData?.license_is_valid ? (
                          <CheckCircle color="success" sx={{ fontSize: 20 }} />
                        ) : licenseKey && licenseData?.license_is_valid === false ? (
                          <ErrorIcon color="error" sx={{ fontSize: 20 }} />
                        ) : null}
                        <Tooltip title={__('Revalidate license', 'flux-plugins-common')}>
                          <IconButton
                            size="small"
                            onClick={handleRevalidateLicense}
                            disabled={isLoading || licenseLoading || activateLicenseMutation.isPending || validateLicenseMutation.isPending || !licenseKey}
                            sx={{ ml: 0.5 }}
                          >
                            <Refresh fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      </Stack>
                    </InputAdornment>
                  ),
                }}
              />
              
              {licenseKey && licenseData?.license_is_valid && licenseData?.license_last_valid_date && (
                <Typography variant="body2" color="text.secondary">
                  {__('Last validated:', 'flux-plugins-common')} {formatLicenseDate(licenseData.license_last_valid_date)}
                </Typography>
              )}

              {licenseKey && licenseData?.license_is_valid && (
                <Alert severity="success">
                  <Typography variant="body2">
                    {__('Your license is active and valid. Premium features are enabled across all Flux Suite plugins.', 'flux-plugins-common')}
                  </Typography>
                </Alert>
              )}
            </Stack>
          </Stack>
        </Grid>

        {/* Second Column: License upsell card */}
        {!(licenseKey && licenseData?.license_is_valid) && (
          <Grid item xs={12} md={6}>
            <Card
              variant="outlined"
              sx={{
                height: '100%',
                border: '1px solid',
                borderColor: 'primary.main',
                backgroundColor: 'action.hover',
              }}
            >
              <CardContent>
                <Stack spacing={2}>
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Star sx={{ color: 'primary.main' }} />
                    <Typography variant="h6" component="h3">
                      {__('Upgrade to Pro', 'flux-plugins-common')}
                    </Typography>
                  </Box>
                  
                  <Typography variant="body2" color="text.secondary">
                    {__('Get more powerful features with Flux Suite Pro:', 'flux-plugins-common')}
                  </Typography>

                  <Stack spacing={1}>
                    <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1 }}>
                      <CheckCircle sx={{ fontSize: '1.2rem', color: 'success.main', mt: 0.25 }} />
                      <Typography variant="body2">
                        {__('AI-Powered Features Across All Plugins', 'flux-plugins-common')}
                      </Typography>
                    </Box>
                    <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1 }}>
                      <CheckCircle sx={{ fontSize: '1.2rem', color: 'success.main', mt: 0.25 }} />
                      <Typography variant="body2">
                        {__('Advanced Automation and Scheduling', 'flux-plugins-common')}
                      </Typography>
                    </Box>
                    <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1 }}>
                      <CheckCircle sx={{ fontSize: '1.2rem', color: 'success.main', mt: 0.25 }} />
                      <Typography variant="body2">
                        {__('CDN Integration for Faster Delivery', 'flux-plugins-common')}
                      </Typography>
                    </Box>
                    <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1 }}>
                      <CheckCircle sx={{ fontSize: '1.2rem', color: 'success.main', mt: 0.25 }} />
                      <Typography variant="body2">
                        {__('Premium Features Across All Flux Suite Plugins', 'flux-plugins-common')}
                      </Typography>
                    </Box>
                  </Stack>

                  <Divider />

                  <Button
                    variant="contained"
                    color="primary"
                    href="https://fluxplugins.com"
                    target="_blank"
                    rel="noopener noreferrer"
                    fullWidth
                    sx={{ fontWeight: 600 }}
                  >
                    {__('Get Your License', 'flux-plugins-common')}
                  </Button>

                  <Typography variant="caption" color="text.secondary" align="center">
                    {__('Single license unlocks all premium features', 'flux-plugins-common')}
                  </Typography>
                </Stack>
              </CardContent>
            </Card>
          </Grid>
        )}
      </Grid>

      {/* Account ID Section - At the bottom for technical support reference */}
      <Box sx={{ mt: 4, pt: 4, borderTop: 1, borderColor: 'divider' }}>
        <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
          {__('Account ID', 'flux-plugins-common')}
        </Typography>
        <Typography variant="caption" color="text.secondary" sx={{ mb: 2, display: 'block' }}>
          {__('Your account ID is used for technical support. Please provide this when', 'flux-plugins-common')}{' '}
          <Link href="https://fluxplugins.com/contact-support/" target="_blank" rel="noopener noreferrer" sx={{ color: 'primary.main' }}>
            {__('contacting support', 'flux-plugins-common')}
          </Link>
          .
        </Typography>
        <TextField
          value={accountIdLoading ? __('Loading...', 'flux-plugins-common') : accountId}
          disabled
          type={showAccountId ? 'text' : 'password'}
          variant="outlined"
          size="small"
          sx={{
            maxWidth: 500,
            '& .MuiInputBase-input': {
              fontFamily: 'monospace',
              fontSize: '0.875rem',
            },
          }}
          InputProps={{
            endAdornment: (
              <InputAdornment position="end">
                <Stack direction="row" spacing={0.5} alignItems="center">
                  <Tooltip title={showAccountId ? __('Hide Account ID', 'flux-plugins-common') : __('Show Account ID', 'flux-plugins-common')}>
                    <IconButton
                      size="small"
                      onClick={() => setShowAccountId(!showAccountId)}
                      edge="end"
                      sx={{ ml: 0.5 }}
                    >
                      {showAccountId ? <VisibilityOff fontSize="small" /> : <Visibility fontSize="small" />}
                    </IconButton>
                  </Tooltip>
                  <Tooltip title={copiedAccountId ? __('Copied!', 'flux-plugins-common') : __('Copy Account ID', 'flux-plugins-common')}>
                    <IconButton
                      size="small"
                      onClick={handleCopyAccountId}
                      disabled={!accountId || accountIdLoading}
                      edge="end"
                    >
                      {copiedAccountId ? <Check fontSize="small" color="success" /> : <ContentCopy fontSize="small" />}
                    </IconButton>
                  </Tooltip>
                </Stack>
              </InputAdornment>
            ),
          }}
        />
      </Box>
    </PageLayout>
  );
};

/**
 * License page with providers
 * This is the entry point that sets up React Query and Material-UI
 */
const LicensePage = () => {
  return (
    <QueryClientProvider client={queryClient}>
      <FluxAppProvider>
        <LicensePageContent />
      </FluxAppProvider>
    </QueryClientProvider>
  );
};

export default LicensePage;

