const path = require('path');
const { createBaseWebpackConfig } = require('./webpack.config.helpers');

/**
 * Webpack configuration for flux-plugins-common
 * 
 * Builds standalone bundles for the common library:
 * - compatibility-dismiss: Simple JS bundle
 * - license-page: React License page bundle (standalone)
 */
module.exports = createBaseWebpackConfig({
  pluginDir: __dirname,
  pluginSlug: 'flux-plugins-common',
  extends: {
    entry: {
      'compatibility-dismiss': './src/assets/js/src/admin/compatibility-dismiss.js',
      'license-page': './src/assets/js/src/admin/license-page.js',
    },
    output: {
      path: path.resolve(__dirname, 'src/assets/js/dist'),
      filename: '[name].bundle.js',
      clean: true,
    },
  },
});
