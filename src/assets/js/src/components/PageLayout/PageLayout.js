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
 * @param {boolean} [props.nested=false] - If true, skip Container/Paper wrapper (for nested use)
 * @returns {JSX.Element} PageLayout component
 */
const PageLayout = ({ title, children, maxWidth = 'lg', nested = false, ...props }) => {
  if (!title && !nested) {
    console.warn('PageLayout: title prop is required when not nested');
  }

  // Header with icon and title (only show when not nested)
  const header = !nested && title ? (
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
      <BrandIcon size={40} sx={{ mr: 2 }} />
      <Typography variant="h4" component="h1" sx={{ m: 0, lineHeight: 1 }}>
        {title}
      </Typography>
    </Box>
  ) : null;

  // If nested, just render children without Container/Paper/Header
  // (parent component handles branding and layout)
  if (nested) {
    return <>{children}</>;
  }

  // Full layout with Container/Paper
  return (
    <Container maxWidth={maxWidth} sx={{ py: 4 }} {...props}>
      <Paper elevation={1} sx={{ p: 4 }}>
        {header}
        {/* Page content */}
        {children}
      </Paper>
    </Container>
  );
};

export default PageLayout;

