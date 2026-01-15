import React, { useState, useEffect } from 'react';
import {
  Typography,
  Box,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Chip,
  TextField,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Pagination,
  Grid,
  Alert,
  Skeleton,
  IconButton,
  Tooltip,
  Switch,
  FormControlLabel,
} from '@mui/material';
import {
  Refresh,
  Search,
} from '@mui/icons-material';
import { __ } from '@wordpress/i18n';
import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useQuery } from '@tanstack/react-query';
import { logsApiService } from '../../services/logsApi';
import { PageLayout } from '../PageLayout';
import { FluxAppProvider } from '../FluxAppProvider';
import StyleShowcase from './StyleShowcase';

/**
 * Logs page component with pagination and filtering
 */
const LogsPageContent = () => {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [level, setLevel] = useState('');
  const [search, setSearch] = useState('');
  const [enableLogging, setEnableLogging] = useState(false);
  const [error, setError] = useState(null);

  // Load initial logging setting
  useEffect(() => {
    const loadSettings = async () => {
      try {
        const response = await logsApiService.getOptions();
        if (response && typeof response === 'object') {
          setEnableLogging(response.enable_logging !== false);
        }
      } catch (err) {
        console.error('Failed to load logging setting:', err);
        setError(__('Failed to load logging setting', 'flux-plugins-common'));
      }
    };

    loadSettings();
  }, []);

  // Fetch logs with React Query (only when logging is enabled)
  const {
    data: logsData,
    isLoading,
    error: logsError,
    refetch,
  } = useQuery({
    queryKey: ['logs', page, perPage, level, search],
    queryFn: () => logsApiService.getLogs({ page, per_page: perPage, level, search }),
    keepPreviousData: true,
    enabled: enableLogging, // Only fetch when logging is enabled
  });

  const handleLoggingToggle = (event) => {
    const newValue = event.target.checked;
    setEnableLogging(newValue);
    setError(null);
    // TODO: Save setting via API when options endpoint is available
  };

  const handlePageChange = (event, newPage) => {
    setPage(newPage);
  };

  const handlePerPageChange = (event) => {
    setPerPage(event.target.value);
    setPage(1); // Reset to first page when changing per page
  };

  const handleLevelChange = (event) => {
    setLevel(event.target.value);
    setPage(1); // Reset to first page when filtering
  };

  const handleSearchChange = (event) => {
    setSearch(event.target.value);
    setPage(1); // Reset to first page when searching
  };

  const handleRefresh = () => {
    refetch();
  };

  const getLevelColor = (level) => {
    switch (level) {
      case 'ERROR':
      case 'CRITICAL':
        return 'error';
      case 'WARNING':
        return 'warning';
      default:
        return 'default';
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
      if (window.wp?.date?.dateI18n) {
        const dateFormat = window.wp?.date?.settings?.formats?.date || 'F j, Y';
        const timeFormat = window.wp?.date?.settings?.formats?.time || 'g:i a';
        const format = `${dateFormat} ${timeFormat}`;
        const date = new Date(dateString + ' UTC');
        return window.wp.date.dateI18n(format, date);
      } else {
        const date = new Date(dateString + ' UTC');
        return date.toLocaleString();
      }
    } catch (e) {
      return dateString;
    }
  };

  const formatContext = (context) => {
    if (!context) return '';
    if (typeof context === 'string') return context;
    return JSON.stringify(context, null, 2);
  };

  if (error) {
    return (
      <PageLayout title={__('Flux Suite - Logs', 'flux-plugins-common')}>
        <Alert severity="error" sx={{ mb: 3 }}>
          {error}
        </Alert>
      </PageLayout>
    );
  }

  return (
    <PageLayout title={__('Flux Suite - Logs', 'flux-plugins-common')}>
      {/* Logging Toggle */}
      <Box sx={{ mb: 3, p: 2, bgcolor: 'background.paper', borderRadius: 1, border: '1px solid', borderColor: 'divider' }}>
        <Grid container justifyContent="space-between" alignItems="center">
          <Grid item>
            <Typography variant="h6" gutterBottom>
              {__('Logging Settings', 'flux-plugins-common')}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {__('Enable or disable system logging. When disabled, no new logs will be created and existing logs will not be displayed.', 'flux-plugins-common')}
            </Typography>
          </Grid>
          <Grid item>
            <FormControlLabel
              control={
                <Switch
                  checked={enableLogging}
                  onChange={handleLoggingToggle}
                  color="primary"
                />
              }
              label={__('Enable Logging', 'flux-plugins-common')}
            />
          </Grid>
        </Grid>
      </Box>

      {/* Show message when logging is disabled */}
      {!enableLogging && (
        <Alert severity="info" sx={{ mb: 3 }}>
          <Typography variant="body2">
            {__('Logging is currently disabled. Enable logging above to view system logs and start recording new log entries.', 'flux-plugins-common')}
          </Typography>
        </Alert>
      )}

      {/* Logs content - only show when logging is enabled */}
      {enableLogging && (
        <>
          <Grid container justifyContent="space-between" alignItems="center" sx={{ mb: 3 }}>
            <Grid item>
              <Typography variant="h6" gutterBottom>
                {__('Log Entries', 'flux-plugins-common')}
              </Typography>
            </Grid>
            <Grid item>
              <Tooltip title={__('Refresh logs', 'flux-plugins-common')}>
                <IconButton onClick={handleRefresh} disabled={isLoading}>
                  <Refresh />
                </IconButton>
              </Tooltip>
            </Grid>
          </Grid>

          {logsError && (
            <Alert severity="error" sx={{ mb: 3 }}>
              {__('Error loading logs:', 'flux-plugins-common')} {logsError?.message || __('Unknown error occurred', 'flux-plugins-common')}
            </Alert>
          )}

          {/* Filters */}
          <Grid container spacing={2} sx={{ mb: 3 }}>
            <Grid item xs={12} sm={6} md={3}>
              <TextField
                fullWidth
                label={__('Search logs', 'flux-plugins-common')}
                value={search}
                onChange={handleSearchChange}
                InputProps={{
                  startAdornment: <Search sx={{ mr: 1, color: 'text.secondary' }} />,
                }}
                size="small"
              />
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <FormControl fullWidth size="small">
                <InputLabel>{__('Log Level', 'flux-plugins-common')}</InputLabel>
                <Select
                  value={level}
                  onChange={handleLevelChange}
                  label={__('Log Level', 'flux-plugins-common')}
                >
                  <MenuItem value="">{__('All Levels', 'flux-plugins-common')}</MenuItem>
                  <MenuItem value="ERROR">{__('Error', 'flux-plugins-common')}</MenuItem>
                  <MenuItem value="WARNING">{__('Warning', 'flux-plugins-common')}</MenuItem>
                  <MenuItem value="CRITICAL">{__('Critical', 'flux-plugins-common')}</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <FormControl fullWidth size="small">
                <InputLabel>{__('Per Page', 'flux-plugins-common')}</InputLabel>
                <Select
                  value={perPage}
                  onChange={handlePerPageChange}
                  label={__('Per Page', 'flux-plugins-common')}
                >
                  <MenuItem value={10}>10</MenuItem>
                  <MenuItem value={20}>20</MenuItem>
                  <MenuItem value={50}>50</MenuItem>
                  <MenuItem value={100}>100</MenuItem>
                </Select>
              </FormControl>
            </Grid>
          </Grid>

          {/* Logs Table */}
          <TableContainer component={Paper}>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{__('Level', 'flux-plugins-common')}</TableCell>
                  <TableCell>{__('Message', 'flux-plugins-common')}</TableCell>
                  <TableCell>{__('Context', 'flux-plugins-common')}</TableCell>
                  <TableCell>{__('Date', 'flux-plugins-common')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {isLoading ? (
                  // Loading skeleton
                  Array.from({ length: perPage }).map((_, index) => (
                    <TableRow key={index}>
                      <TableCell>
                        <Skeleton variant="rectangular" width={60} height={24} sx={{ borderRadius: 1 }} />
                      </TableCell>
                      <TableCell>
                        <Skeleton variant="text" width="80%" />
                      </TableCell>
                      <TableCell>
                        <Skeleton variant="text" width="60%" />
                      </TableCell>
                      <TableCell>
                        <Skeleton variant="text" width={120} />
                      </TableCell>
                    </TableRow>
                  ))
                ) : logsData?.data?.length > 0 ? (
                  logsData.data.map((log) => (
                    <TableRow key={log.id} hover>
                      <TableCell>
                        <Chip
                          label={log.level}
                          color={getLevelColor(log.level)}
                          size="small"
                        />
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2" sx={{ wordBreak: 'break-word' }}>
                          {log.message}
                        </Typography>
                      </TableCell>
                      <TableCell>
                        {log.context && (
                          <Typography
                            variant="caption"
                            sx={{
                              fontFamily: 'monospace',
                              fontSize: '0.75rem',
                              wordBreak: 'break-all',
                              display: 'block',
                              maxWidth: 200,
                              overflow: 'hidden',
                              textOverflow: 'ellipsis',
                              whiteSpace: 'nowrap',
                            }}
                            title={formatContext(log.context)}
                          >
                            {formatContext(log.context)}
                          </Typography>
                        )}
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2" color="text.secondary">
                          {formatDate(log.created_at)}
                        </Typography>
                      </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={4} align="center">
                      <Typography variant="body2" color="text.secondary">
                        {__('No logs found', 'flux-plugins-common')}
                      </Typography>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </TableContainer>

          {/* Pagination */}
          {logsData?.data && logsData.total_pages > 1 && (
            <Box sx={{ display: 'flex', justifyContent: 'center', mt: 3 }}>
              <Pagination
                count={logsData.total_pages}
                page={page}
                onChange={handlePageChange}
                color="primary"
                showFirstButton
                showLastButton
              />
            </Box>
          )}

          {/* Pagination Info */}
          {logsData?.data && (
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mt: 2 }}>
              <Typography variant="body2" color="text.secondary">
                {__('Showing', 'flux-plugins-common')} {((page - 1) * perPage) + 1} - {Math.min(page * perPage, logsData.total)} {__('of', 'flux-plugins-common')} {logsData.total} {__('logs', 'flux-plugins-common')}
              </Typography>
            </Box>
          )}
        </>
      )}
    </PageLayout>
  );
};

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
 * Logs page router wrapper
 * Handles hash-based routing for logs and style showcase pages
 */
const LogsPageRouter = () => {
  return (
    <HashRouter>
      <Routes>
        <Route path="/" element={<LogsPageContent />} />
        <Route path="/style" element={<StyleShowcase />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </HashRouter>
  );
};

/**
 * Logs page with providers
 * This is the entry point that sets up React Query and Material-UI
 */
const LogsPage = () => {
  return (
    <QueryClientProvider client={queryClient}>
      <FluxAppProvider>
        <LogsPageRouter />
      </FluxAppProvider>
    </QueryClientProvider>
  );
};

export default LogsPage;

