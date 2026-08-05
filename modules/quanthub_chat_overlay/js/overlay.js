/**
 * @file
 * Quanthub Chat Overlay behaviors.
 */

(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.quanthubChatOverlay = {
    overlays: [],
    attach(context) {
      const self = this;
      if (!drupalSettings.dialChatUrl) {
        return;
      }
      once('dial-ai-chat-overlay', '.ai-dial-chat-overlay-container', context).forEach((container) => {
        const options = {
          domain: drupalSettings.dialChatUrl,
          theme: 'light',
        };
        if (drupalSettings.dialDefaultModel) {
          options.modelId = drupalSettings.dialDefaultModel;
        }
        if (drupalSettings.dialEnabledFeatures) {
          options.enabledFeatures = drupalSettings.dialEnabledFeatures.split(',');
        }

        const overlay = new window.AIDialChatOverlay.ChatOverlay(container.querySelector('.ai-dial-chat-overlay'), options);
        self.overlays.push(overlay);

        container.querySelector('.ai-dial-chat-overlay-trigger')?.addEventListener('click', (e) => {
          e.preventDefault();
          document.body.classList.toggle('ai-dial-chat-overlay-open');
        });

        container.querySelector('.ai-dial-chat-overlay-close-btn')?.addEventListener('click', (e) => {
          e.preventDefault();
          document.body.classList.remove('ai-dial-chat-overlay-open', 'ai-dial-chat-overlay-fullscreen');
        });

        container.querySelector('.ai-dial-chat-overlay-fullscreen-btn')?.addEventListener('click', (e) => {
          e.preventDefault();
          document.body.classList.toggle('ai-dial-chat-overlay-fullscreen');
        });
      });
    },
  };
})(Drupal, drupalSettings, once);
