/**
 * @file
 * Quanthub Chat Overlay behaviors.
 */

(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.quanthubChatOverlay = {
    overlays: {},
    attach(context) {
      const self = this;
      if (!drupalSettings.dialChatUrl) {
        return;
      }
      once('dial-ai-chat-overlay', '.ai-dial-chat-overlay-container', context).forEach((container) => {
        const panel = container.querySelector('.ai-dial-chat-overlay');
        if (!panel) {
          return;
        }

        const overlayId = 'overlay-' + Object.keys(self.overlays).length;
        panel.dataset.overlayId = overlayId;
        self.overlays[overlayId] = null;

        container.querySelector('.ai-dial-chat-overlay-trigger')?.addEventListener('click', (e) => {
          e.preventDefault();
          self.initOverlay(panel, overlayId);
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

        container.querySelector('.ai-dial-chat-overlay-resize-handle')?.addEventListener('pointerdown', (e) => {
          e.preventDefault();

          const rect = panel.getBoundingClientRect();
          const start = {x: e.clientX, y: e.clientY};
          const size = {x: rect.width, y: rect.height};

          const onResize = (e) => {
            panel.style.width = (start.x - e.clientX + size.x) + 'px';
            panel.style.height = (start.y - e.clientY + size.y) + 'px';
          };

          const stopResize = (e) => {
            document.removeEventListener('pointermove', onResize);
            document.removeEventListener('pointerup', stopResize);
            document.removeEventListener('pointercancel', stopResize);
            panel.style.removeProperty('transition');
            document.body.releasePointerCapture(e.pointerId);
          }

          document.body.setPointerCapture(e.pointerId);
          panel.style.setProperty('transition', 'none', 'important');
          document.addEventListener('pointermove', onResize);
          document.addEventListener('pointerup', stopResize);
          document.addEventListener('pointercancel', stopResize);
        });
      });
    },
    initOverlay(root, id) {
      if (this.overlays[id]) {
        return;
      }

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

      const overlay = new window.AIDialChatOverlay.ChatOverlay(root, options);
      root.dataset.overlayId = id;
      this.overlays[id] = overlay;
    },
  };

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.body.classList.remove('ai-dial-chat-overlay-fullscreen');
    }
  });
})(Drupal, drupalSettings, once);
