const path = require('path');
const { createBaseWebpackConfig } = require('./webpack.config.helpers');

/**
 * Base webpack configuration for flux-plugins-common
 * 
 * This is the base configuration that plugins can extend.
 * Plugins should use webpack.config.helpers.js to merge this with their own config.
 */
module.exports = createBaseWebpackConfig({
  pluginDir: __dirname,
  pluginSlug: 'flux-plugins-common',
  extends: {
    entry: {
      'compatibility-dismiss': './assets/js/src/admin/compatibility-dismiss.js',
    },
    output: {
      path: path.resolve(__dirname, 'assets/js/dist'),
      filename: '[name].bundle.js',
      clean: true,
    },
    externals: {
      jquery: 'jQuery',
    },
  },
});

