/**
 * Shared Flux Suite Pro upsell card (License page, plugin overviews).
 *
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 *
 * @since 1.1.0
 */

import React from 'react';
import {
  Typography,
  Box,
  Button,
  Card,
  CardContent,
  Divider,
  Stack,
} from '@mui/material';
import { CheckCircle, Star } from '@mui/icons-material';
import { __ } from '@wordpress/i18n';

const DEFAULT_BULLET_KEYS = [
  __('AI-Powered Features Across All Plugins', 'flux-plugins-common'),
  __('Advanced Automation and Scheduling', 'flux-plugins-common'),
  __('CDN Integration for Faster Delivery', 'flux-plugins-common'),
  __('Premium Features Across All Flux Suite Plugins', 'flux-plugins-common'),
];

/**
 * @param {object} props
 * @param {string} [props.title]
 * @param {string} [props.intro]
 * @param {string[]} [props.bullets] Override default suite bullets (plugin-specific claims).
 * @param {string} [props.ctaLabel]
 * @param {string} [props.ctaHref]
 * @param {string} [props.caption]
 */
export default function UpsellCard({
  title,
  intro,
  bullets,
  ctaLabel,
  ctaHref,
  caption,
}) {
  const titleUse = title ?? __('Upgrade to Pro', 'flux-plugins-common');
  const introUse =
    intro ?? __('Get more powerful features with Flux Suite Pro:', 'flux-plugins-common');
  const bulletsUse = Array.isArray(bullets) && bullets.length > 0 ? bullets : DEFAULT_BULLET_KEYS;
  const ctaLabelUse = ctaLabel ?? __('Get Your License', 'flux-plugins-common');
  const ctaHrefUse = ctaHref ?? 'https://fluxplugins.com';
  const captionUse =
    caption ?? __('Single license unlocks all premium features', 'flux-plugins-common');

  return (
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
              {titleUse}
            </Typography>
          </Box>

          <Typography variant="body2" color="text.secondary">
            {introUse}
          </Typography>

          <Stack spacing={1}>
            {bulletsUse.map((line, i) => (
              <Box key={i} sx={{ display: 'flex', alignItems: 'flex-start', gap: 1 }}>
                <CheckCircle sx={{ fontSize: '1.2rem', color: 'success.main', mt: 0.25 }} />
                <Typography variant="body2">{line}</Typography>
              </Box>
            ))}
          </Stack>

          <Divider />

          <Button
            variant="contained"
            color="primary"
            href={ctaHrefUse}
            target="_blank"
            rel="noopener noreferrer"
            fullWidth
            sx={{ fontWeight: 600 }}
          >
            {ctaLabelUse}
          </Button>

          <Typography variant="caption" color="text.secondary" align="center">
            {captionUse}
          </Typography>
        </Stack>
      </CardContent>
    </Card>
  );
}
