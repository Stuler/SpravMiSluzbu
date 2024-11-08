const path = require('path');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const webpack = require("webpack");
import MiniCssExtractPlugin from "mini-css-extract-plugin";

module.exports = (env, argv) => {
	const isDev = env.WEBPACK_SERVE;
	return {
		entry: async function () {
			return {
				admin: [
					path.resolve(__dirname, "www", "assets_admin", "admin.sass"),
					path.resolve(__dirname, "www", "assets_admin", "admin.js"),
				],
				front: [
					path.resolve(__dirname, "www", "assets_front", "scss", "main.scss"),
					path.resolve(__dirname, "www", "assets_front", "front.js"),
				],
			}
		},
		mode: isDev ? "development" : "production",
		cache: isDev,
		devtool: isDev ? 'eval-cheap-module-source-map' : 'hidden-source-map',
		watchOptions: {
			ignored: "/node_modules/"
		},
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
			}
		},
		resolve: {
			extensions: [".ts", ".tsx", ".js"],
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
		].concat(isDev && [
			new webpack.HotModuleReplacementPlugin(),
			new HtmlWebpackPlugin({
				title: 'Hot Module Replacement',
			}),
		]),
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
							options: {
								sourceMap: true
							}
						},
						'resolve-url-loader',
						{
							loader: 'sass-loader',
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
					test: /\.(ts|tsx)$/,
					exclude: /node_modules\/(?!@contributte)/,
					use: {
						loader: 'ts-loader',
						options: {
							allowTsInNodeModules: true  // Pridaj túto možnosť
						}
					}
				},
				{
					test: /\.(js)$/,
					exclude: /node_modules/,
					use: 'babel-loader'
				}
			]
		}
	}
};
