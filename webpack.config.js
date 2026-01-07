const path = require('path');

module.exports = {
	entry: {
		'compatibility-dismiss': './assets/js/src/admin/compatibility-dismiss.js',
	},
	output: {
		path: path.resolve(__dirname, 'assets/js/dist'),
		filename: '[name].bundle.js',
	},
	module: {
		rules: [
			{
				test: /\.js$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: ['@babel/preset-env'],
					},
				},
			},
		],
	},
	externals: {
		jquery: 'jQuery',
	},
	mode: process.env.NODE_ENV === 'production' ? 'production' : 'development',
	devtool: process.env.NODE_ENV === 'production' ? false : 'source-map',
};

