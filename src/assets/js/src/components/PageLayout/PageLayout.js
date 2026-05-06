/**
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * @since 1.0.0 Added externally managed source notice.
 */

import React from 'react';
import { Container, Paper, Box, Typography } from '@mui/material';
import BrandIcon from './BrandIcon';

/**
 * PageLayout component for consistent branding and layout across Flux Plugins
 * 
 * @since 1.0.0
 * @param {Object} props - Component props
 * @param {string} props.title - Plugin name to display next to icon (required)
 * @param {React.ReactNode} props.children - Page content to render
 * @param {string} [props.maxWidth='lg'] - Container max width
 * @returns {JSX.Element} PageLayout component
 */
const PageLayout = ({ title, children, maxWidth = 'lg', ...props }) => {
  if (!title) {
    console.warn('PageLayout: title prop is required');
  }

  return (
    <Container maxWidth={maxWidth} sx={{ py: 4 }} {...props}>
      <Paper elevation={1} sx={{ p: 4 }}>
        {/* Header with icon and title */}
        <Box
          sx={{
            display: 'flex',
            alignItems: 'center',
            mb: 4,
            pb: 2,
            borderBottom: 1,
            borderColor: 'divider',
          }}
        >
          <BrandIcon size={28} sx={{ mr: 2 }} />
          <Typography variant="h4" component="h1" sx={{ m: 0, p: 0, lineHeight: 1 }}>
            {title}
          </Typography>
        </Box>
        
        {/* Page content */}
        {children}
      </Paper>
    </Container>
  );
};

export default PageLayout;

