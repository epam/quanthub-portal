(function ($, Drupal) {
  Drupal.behaviors.quantHubPowerBiRenderer = {
    attach: function (context, settings) {
      $(document).ready(function() {
        const models = window["powerbi-client"].models;

        $(".field--type-quanthub-powerbi-embed div[data-report-id!=''][data-report-id]").each(function(index) {
          let powerBiData = $(this)[index].dataset;

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
          context.report.on("rendered", function () {
            applyLocaleBookmark(context);
            context.report.off("rendered");
          });

          context.report.iframe.onload = () => {
            powerbi_embed_customizeReport($, context,  powerBiData.reportWidth, powerBiData.reportHeight, powerBiData.reportTitle);
          };
        })
      })
    }
  };
})(jQuery, Drupal, drupalSettings );
