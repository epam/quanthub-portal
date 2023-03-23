const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
  entry: './src/styles.scss',
  plugins: [
    new MiniCssExtractPlugin({
      runtime: false,
      insert: () => {},
      filename: 'styles.css',
    }),
  ],
  module: {
    rules: [
      {
        test: /\.s[ac]ss$/i,
        use: [
          MiniCssExtractPlugin.loader,
          'css-loader',
          'postcss-loader',
          'sass-loader',
        ],
      },
    ],
  },
};
