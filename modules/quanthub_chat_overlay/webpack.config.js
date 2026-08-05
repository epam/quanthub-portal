const path = require('path');

module.exports = {
  entry: {
    'ai-dial-chat-overlay': {
      import: '@epam/ai-dial-chat-overlay',
      library: {
        name: 'AIDialChatOverlay',
        type: 'window',
      },
    },
  },
  output: {
    path: path.resolve(__dirname, 'js'),
    filename: '[name].js'
  },
};
