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

        const panel = container.querySelector('.ai-dial-chat-overlay');
        if (!panel) {
          return;
        }

        const overlay = new window.AIDialChatOverlay.ChatOverlay(panel, options);
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

        container.querySelector('.ai-dial-chat-overlay-resize-handle')?.addEventListener('pointerdown', (e) => {
          e.preventDefault();

          const handle = e.currentTarget;
          const rect = panel.getBoundingClientRect();
          const start = {x: e.clientX, y: e.clientY};
          const size = {x: rect.width, y: rect.height};

          const onResize = (e) => {
            panel.style.width = Math.max(start.x - e.clientX + size.x, 400) + 'px';
            panel.style.height = Math.max(start.y - e.clientY + size.y, 400) + 'px';
          };

          const glassPane = document.createElement('div');
          glassPane.setAttribute('aria-hidden', 'true');
          Object.assign(glassPane.style, {
            position: 'fixed',
            inset: '0',
            zIndex: '1000',
            cursor: 'nwse-resize',
          });

          handle.setPointerCapture(e.pointerId);
          document.body.appendChild(glassPane);
          panel.style.setProperty('transition', 'none', 'important');

          const stopResize = () => {
            if (handle.hasPointerCapture(e.pointerId)) {
              handle.releasePointerCapture(e.pointerId);
            }
            handle.removeEventListener('pointermove', onResize);
            handle.removeEventListener('pointerup', stopResize);
            handle.removeEventListener('pointercancel', stopResize);
            window.removeEventListener('blur', stopResize);
            glassPane.remove();
            panel.style.removeProperty('transition');
          };

          handle.addEventListener('pointermove', onResize);
          handle.addEventListener('pointerup', stopResize);
          handle.addEventListener('pointercancel', stopResize);
          window.addEventListener('blur', stopResize);
        });
      });
    },
  };

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.body.classList.remove('ai-dial-chat-overlay-fullscreen');
    }
  });
})(Drupal, drupalSettings, once);
