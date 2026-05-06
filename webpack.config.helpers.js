/**
 * IMPORTANT: This file is part of the externally managed `stratease/flux-plugins-common` library.
 * Do not edit copies inside consuming plugins (including Strauss-prefixed `vendor-prefixed/`).
 *
 * @since 1.0.0 Added externally managed source notice.
 */

const path = require('path');
const fs = require('fs');

/**
 * Get the base directory for flux-plugins-common
 * This is used to resolve paths relative to the common library
 */
function getCommonBaseDir() {
  // When building common lib itself, use __dirname
  if (fs.existsSync(path.join(__dirname, 'package.json'))) {
    return __dirname;
  }
  // When used by plugins, try vendor-prefixed
  const vendorPath = path.resolve(process.cwd(), 'vendor-prefixed/stratease/flux-plugins-common');
  if (fs.existsSync(vendorPath) && fs.existsSync(path.join(vendorPath, 'package.json'))) {
    return vendorPath;
  }
  return __dirname;
}

/**
 * Create base webpack configuration that can be merged by plugins
 * 
 * @param {Object} options - Configuration options
 * @param {string} options.pluginDir - Plugin directory path
 * @param {string} options.pluginSlug - Plugin slug (e.g., 'flux-media-optimizer')
 * @param {Object} options.extends - Additional webpack config to merge
 * @returns {Object} Webpack configuration object
 */
function createBaseWebpackConfig(options = {}) {
  const { pluginDir, pluginSlug, extends: extendsConfig = {} } = options;
  const commonBaseDir = getCommonBaseDir();
  const isBuildingCommonLib = pluginDir === __dirname;

  // Base config for React and WordPress
  const baseConfig = {
    resolve: {
      extensions: ['.js', '.jsx', '.json'],
      alias: {
        // More specific aliases must come first
        '@flux-plugins-common/images': path.resolve(commonBaseDir, 'src/assets/images'),
        '@flux-plugins-common': path.resolve(commonBaseDir, 'src/assets/js/src'),
        ...(pluginDir && { [`@${pluginSlug}`]: path.resolve(pluginDir, 'assets/js/src') }),
      },
    },
    module: {
      rules: [
        {
          test: /\.jsx?$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: [
                ['@babel/preset-env', {
                  targets: {
                    browsers: ['> 1%', 'last 2 versions'],
                  },
                }],
                // Always include React preset since common lib has React components
                ['@babel/preset-react', { runtime: 'automatic' }],
              ],
              plugins: [
                // Use module resolver for aliases (works for both common lib and plugins)
                [
                  'babel-plugin-module-resolver',
                  {
                    alias: {
                      // More specific aliases must come first
                      '@flux-plugins-common/images': path.resolve(commonBaseDir, 'src/assets/images'),
                      '@flux-plugins-common': path.resolve(commonBaseDir, 'src/assets/js/src'),
                      ...(pluginDir && { [`@${pluginSlug}`]: path.resolve(pluginDir, 'assets/js/src') }),
                    },
                  },
                ],
              ],
            },
          },
        },
        {
          test: /\.css$/i,
          use: ['style-loader', 'css-loader'],
        },
        {
          test: /\.(png|jpe?g|gif|svg|webp)$/i,
          type: 'asset/resource',
          generator: {
            filename: 'images/[name][ext]',
          },
        },
      ],
    },
    externals: {
      // WordPress globals
      'wp': 'wp',
      'jquery': 'jQuery',
      '@wordpress/components': 'wp.components',
      '@wordpress/data': 'wp.data',
      '@wordpress/element': 'wp.element',
      '@wordpress/hooks': 'wp.hooks',
      '@wordpress/i18n': 'wp.i18n',
      '@wordpress/notices': 'wp.notices',
      '@wordpress/api-fetch': 'wp.apiFetch',
    },
    mode: process.env.NODE_ENV === 'production' ? 'production' : 'development',
    devtool: process.env.NODE_ENV === 'production' ? false : 'source-map',
  };

  // Simple merge - webpack will handle deep merging of resolve, module, etc.
  return {
    ...baseConfig,
    ...extendsConfig,
    resolve: {
      ...baseConfig.resolve,
      ...extendsConfig.resolve,
      alias: {
        ...baseConfig.resolve.alias,
        ...(extendsConfig.resolve?.alias || {}),
      },
    },
    module: {
      ...baseConfig.module,
      ...extendsConfig.module,
      rules: [
        ...baseConfig.module.rules,
        ...(extendsConfig.module?.rules || []),
      ],
    },
    externals: {
      ...baseConfig.externals,
      ...(extendsConfig.externals || {}),
    },
  };
}

module.exports = {
  getCommonBaseDir,
  createBaseWebpackConfig,
};
