const path = require('path');

const fs = require('fs');

/**
 * Get the base directory for flux-plugins-common
 * This is used to resolve paths relative to the common library
 */
function getCommonBaseDir() {
  // Try to find flux-plugins-common in node_modules or vendor-prefixed
  // This works when the library is installed via Composer
  const possiblePaths = [
    path.resolve(__dirname),
    path.resolve(process.cwd(), 'vendor-prefixed/stratease/flux-plugins-common'),
  ];

  for (const possiblePath of possiblePaths) {
    if (fs.existsSync(possiblePath) && fs.existsSync(path.join(possiblePath, 'package.json'))) {
      return possiblePath;
    }
  }

  // Fallback to __dirname
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

  // Base config for React and WordPress
  const baseConfig = {
    resolve: {
      extensions: ['.js', '.jsx', '.json'],
      alias: {
        // Alias for shared components from flux-plugins-common
        // Assets are now in src/assets/ so Strauss will copy them
        '@flux-plugins-common': path.resolve(commonBaseDir, 'src/assets/js/src'),
        // Plugin-specific alias (can be overridden by extendsConfig)
        [`@${pluginSlug}`]: pluginDir ? path.resolve(pluginDir, 'assets/js/src') : undefined,
      },
    },
    module: {
      rules: [
        {
          test: /\.jsx?$/,
          exclude: [
            /node_modules/,
            // Exclude non-React files from React preset
            /src\/assets\/js\/src\/admin\/(attachment|compatibility-dismiss)\.js$/,
          ],
          use: {
            loader: 'babel-loader',
            options: {
              presets: [
                ['@babel/preset-env', {
                  targets: {
                    browsers: ['> 1%', 'last 2 versions', 'ie >= 11'],
                  },
                }],
                ['@babel/preset-react', {
                  runtime: 'automatic',
                }],
              ],
              plugins: [
                [
                  'babel-plugin-module-resolver',
                  {
                    root: ['./src/assets/js/src'],
                    alias: {
                      '@flux-plugins-common': path.resolve(commonBaseDir, 'src/assets/js/src'),
                      [`@${pluginSlug}`]: pluginDir ? path.resolve(pluginDir, 'assets/js/src') : undefined,
                    },
                  },
                ],
              ],
            },
          },
        },
        {
          test: /\.js$/,
          exclude: /node_modules/,
          include: [
            // Non-React files that need Babel but not React preset
            /src\/assets\/js\/src\/admin\/(attachment|compatibility-dismiss)\.js$/,
          ],
          use: {
            loader: 'babel-loader',
            options: {
              presets: [
                ['@babel/preset-env', {
                  targets: {
                    browsers: ['> 1%', 'last 2 versions', 'ie >= 11'],
                  },
                }],
              ],
              plugins: [
                [
                  'babel-plugin-module-resolver',
                  {
                    root: ['./src/assets/js/src'],
                    alias: {
                      '@flux-plugins-common': path.resolve(commonBaseDir, 'src/assets/js/src'),
                      [`@${pluginSlug}`]: pluginDir ? path.resolve(pluginDir, 'assets/js/src') : undefined,
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

  // Merge with extends config using webpack-merge pattern
  return mergeConfig(baseConfig, extendsConfig);
}

/**
 * Simple deep merge utility for webpack config objects
 * Handles arrays specially (replaces instead of merging)
 */
function mergeConfig(base, extendsConfig) {
  const result = { ...base };

  for (const key in extendsConfig) {
    if (extendsConfig.hasOwnProperty(key)) {
      const baseValue = result[key];
      const extendsValue = extendsConfig[key];

      if (key === 'alias' && baseValue && typeof baseValue === 'object' && typeof extendsValue === 'object') {
        // Merge alias objects
        result[key] = { ...baseValue, ...extendsValue };
      } else if (Array.isArray(baseValue) && Array.isArray(extendsValue)) {
        // Replace arrays (webpack rules, plugins, etc.)
        result[key] = extendsValue;
      } else if (typeof baseValue === 'object' && baseValue !== null && typeof extendsValue === 'object' && extendsValue !== null && !Array.isArray(extendsValue)) {
        // Recursively merge objects
        result[key] = mergeConfig(baseValue, extendsValue);
      } else {
        // Override with extends value
        result[key] = extendsValue;
      }
    }
  }

  return result;
}

module.exports = {
  getCommonBaseDir,
  createBaseWebpackConfig,
  mergeConfig,
};

