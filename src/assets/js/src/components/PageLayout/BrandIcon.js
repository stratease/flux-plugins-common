import React from 'react';
import { Box } from '@mui/material';

/**
 * BrandIcon component for displaying the Flux brand icon
 * 
 * @since 1.0.0
 * @param {Object} props - Component props
 * @param {number} [props.size=40] - Icon size in pixels
 * @param {Object} [props.sx] - Additional MUI sx styles
 * @returns {JSX.Element} BrandIcon component
 */
const BrandIcon = ({ size = 40, sx = {}, ...props }) => {
  // Use require to load the image asset
  // Component location: src/assets/js/src/components/PageLayout/BrandIcon.js
  // Image location: src/assets/images/cropped-flux-icon-rounded-square.webp
  // Using alias for cleaner imports - webpack resolves this via resolve.alias
  const iconPath = require('@flux-plugins-common/images/cropped-flux-icon-rounded-square.webp');

  return (
    <Box
      component="img"
      src={iconPath}
      alt="Flux Suite"
      sx={{
        width: size,
        height: size,
        display: 'block',
        ...sx,
      }}
      {...props}
    />
  );
};

export default BrandIcon;

