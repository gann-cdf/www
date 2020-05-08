require('dotenv').config();
const path = require('path');
const {CleanWebpackPlugin} = require("clean-webpack-plugin");
const HtmlWebpackPlugin = require('html-webpack-plugin');
const CopyWebPackPlugin = require('copy-webpack-plugin');
const Dotenv = require('dotenv-webpack');

const devServerPort = 8080;
module.exports = (env = {}) => {
  if (env.local) {
    process.env.ROOT_PATH = '';
  }
  const config = {
    entry: [
      './src/requisition/requisition.js'
    ],
    output: {
      path: path.resolve(__dirname, 'html'),
      publicPath: process.env.ROOT_PATH,
      filename: 'bundle.js'
    },
    module: {
      rules: [
        {
          test: /\.js$/i,
          exclude: /node_modules/,
          use: {
            loader: "babel-loader"
          }
        },
        {
          test: /\.css$/i,
          use: ["style-loader", "css-loader"]
        },
        {
          test: /\.html?$/i,
          use: "html-loader"
        }
      ]
    },
    plugins: [
      new CleanWebpackPlugin(),
      new Dotenv({
        defaults: true
      }),
      new CopyWebPackPlugin([
        {
          from: 'template',
        }
      ]),
      new HtmlWebpackPlugin({
        template: './template/requisition.html',
        filename: "requisition.html"
      })
    ]
  };
  if (env.local) {
    // TODO some more advanced local debugging scripts in package.json would be keen
    config.optimization = {
      minimize: false
    };
    config.devtool = 'inline-source-map';
    config.devServer = {
      contentBase: './html',
      host: 'robotics.lvh.me',
      port: devServerPort,
      https: false,
      headers: {'Access-Control-Allow-Origin': '*'},
      historyApiFallback: true
    };
  } else {
    // do nothing
  }
  return config;
};
