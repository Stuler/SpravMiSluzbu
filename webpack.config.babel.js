const path = require('path');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const webpack = require("webpack");
import MiniCssExtractPlugin from "mini-css-extract-plugin";

module.exports = (env, argv) => {
	const isDev = env.WEBPACK_SERVE;
	return {
		entry: {
			admin: [
				path.resolve(__dirname, "www", "assets_admin", "admin.sass"),
				path.resolve(__dirname, "www", "assets_admin", "admin.js"),
			],
			front: [
				path.resolve(__dirname, "www", "assets_front", "scss", "main.scss"),
				path.resolve(__dirname, "www", "assets_front", "front.tsx"),
			],
		},
		ignoreWarnings: [
			(warning) => {
				const message = typeof warning === 'string' ? warning : warning.message || '';
				return message.includes('The legacy JS API is deprecated and will be removed in Dart Sass 2.0.0');
			}
		],
		mode: isDev ? "development" : "production",
		cache: isDev,
		devtool: isDev ? 'eval-cheap-module-source-map' : 'hidden-source-map',
		watchOptions: {
			ignored: "/node_modules/"
		},
		stats: {warnings: false},
		devServer: {
			static: [
				"./www/assets_admin",
				"./www/assets_front",
				"./images",
			],
			compress: true,
			port: 8080,
			client: {
				reconnect: 5,
			},
			headers: {
				"Access-Control-Allow-Origin": "*",
			},
			devMiddleware: {
				publicPath: '/bundle/',    // <<< tell dev-server to serve bundles at /bundle/
			},
			proxy: [
				{
					context: (path) => true, // <<< match everything
					target: 'http://localhost:8000',
					changeOrigin: true,
				}
			]
		},
		resolve: {
			extensions: [".ts", ".tsx", ".js", ".jsx"],  // ✅ Added .jsx
			alias: {
				'@': path.resolve(__dirname, 'assets/js'),
				'~': path.resolve(__dirname, 'node_modules')
			}
		},
		optimization: {
			emitOnErrors: false,
		},
		plugins: [
			new MiniCssExtractPlugin({
				filename: "[name].bundle.css"
			}),
			new webpack.ProvidePlugin({
				naja: ['naja', 'default'],
			}),
			new webpack.ProvidePlugin({
				'window.Nette': 'nette-forms',
				$: "jquery",
				jQuery: "jquery",
				'window.jQuery': 'jquery',
				'moment': 'moment'
			}),
			new webpack.DefinePlugin({
				'process.env': {
					STRIPE_PUBLIC_KEY: JSON.stringify(process.env.STRIPE_PUBLIC_KEY),
				},
			}),
			// ✅ Removed React ProvidePlugin since it may not be necessary
		].concat(isDev ? [
			new webpack.HotModuleReplacementPlugin(),
			new HtmlWebpackPlugin({
				title: 'Hot Module Replacement',
			}),
		] : []),
		output: {
			filename: '[name].bundle.js',
			path: path.resolve(__dirname, 'www/bundle'),
			clean: true,
			publicPath: '/bundle/',
			assetModuleFilename: 'assets/[hash][ext][query]'
		},
		module: {
			rules: [
				{
					test: /\.(sass|scss)$/,
					use: [
						MiniCssExtractPlugin.loader,
						{
							loader: 'css-loader',
							options: {sourceMap: true}
						},
						'resolve-url-loader',
						{
							loader: 'sass-loader',
							options: {
								sourceMap: true,
								sassOptions: {
									quietDeps: true,  // 🔇 Suppresses deprecated dependency warnings
								}
							}
						}
					]
				},
				{
					test: /\.css$/,
					use: [MiniCssExtractPlugin.loader, 'css-loader']
				},
				{
					test: /\.(png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot)$/,
					type: 'asset/resource',
					generator: {
						filename: 'assets/[hash][ext][query]'
					}
				},
				{
					test: require.resolve("jquery"),
					loader: "expose-loader",
					options: {
						exposes: ["$", "jQuery"],
					},
				},
				{
					test: /\.(js|jsx|ts|tsx)$/,  // ✅ Only one rule for all
					exclude: /node_modules/,
					use: {
						loader: 'babel-loader',
						options: {
							presets: [
								"@babel/preset-env",
								"@babel/preset-react",
								"@babel/preset-typescript"
							],
							plugins: [
								["@babel/plugin-proposal-decorators", {"legacy": true}],
								["@babel/plugin-proposal-class-properties", {"loose": true}]
							]
						}
					}
				}
			]
		}
	}
};
