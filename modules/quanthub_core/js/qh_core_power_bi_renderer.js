(function ($, Drupal) {
  Drupal.behaviors.quantHubPowerBiRenderer = {
    attach: function (context, settings) {
      const models = window["powerbi-client"].models;

      $(".field--type-quanthub-powerbi-embed div[data-report-id!=''][data-report-id]").each(function(index) {
        if (this.dataset) {
          let powerBiData = this.dataset;

          const weight = powerBiData.reportWidth <= 0 ? '100%' : powerBiData.reportWidth + 'px';
          const height = powerBiData.reportHeight <= 0 ? powerBiData.reportWidth * (19 / 32) : powerBiData.reportHeight;

          $(this)
            .children('div')
            .css('height', height)
            .css('max-width', weight);

          const context = {
            "reportId": powerBiData.reportId,
            "selector": powerBiData.selector,
            "tokenExpiration": powerBiData.tokenExpiration,
            "extraDatasets": powerBiData.extradatasets,
          };

          const pageName = powerBiData.reportPage;
          const language = powerBiData.reportLanguage;

          let settings = {
            filterPaneEnabled: false
          };
          if (pageName) {
            settings = {
              filterPaneEnabled: false,
              localeSettings: { language },
              panes: {
                pageNavigation: {
                  visible: false
                }
              },
              layoutType: models.LayoutType.Custom,
              customLayout: {
                displayOption: models.DisplayOption.FitToWidth,
              }
            };
          }

          context.report = powerbi.embed($('#' + powerBiData.selector).get(0), {
            type: powerBiData.embedType,
            id: context.reportId,
            pageName: pageName,
            visualName: powerBiData.reportVisual,
            embedUrl: powerBiData.embedUrl,
            tokenType: models.TokenType.Embed,
            permissions: models.Permissions.All,
            accessToken: powerBiData.token,
            settings: settings
          });
          context.report.off("rendered");
          context.report.on("rendered", function() {
            applyLocaleBookmark(context);
            context.report.off("rendered");
          });

          context.report.iframe.onload = () => {
            powerbi_embed_customizeReport($, context, powerBiData.reportTitle);
          };
        }
      })
    }
  };
})(jQuery, Drupal, drupalSettings );
