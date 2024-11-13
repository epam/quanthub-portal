const INTERVAL_TIME = 30 * 1000;
const TIME_TO_UPDATE = 2 * 60 * 1000;

function applyLocaleBookmark(context) {
  context.report.bookmarksManager.getBookmarks()
    .then(bookmarks => bookmarks.forEach(bm => {
      if (bm.displayName === context.report.config.settings.localeSettings.language) {
        context.report.bookmarksManager.apply(bm.name);
      }
    }));
}

function checkTokenAndUpdate($, context) {
  if (context.tokenExpiration == null) {
    updateToken($, context);
    return;
  }

  const currentTime = Date.now();
  const expiration = Date.parse(context.tokenExpiration);
  const timeUntilExpiration = expiration - currentTime;

  if (timeUntilExpiration <= TIME_TO_UPDATE) {
    updateToken($, context);
  }
}

function updateToken($, context) {
  console.log('updating embed token');
  $.ajax({
      type: "POST",
      dataType: "json",
      contentType: "application/json",
      processData: false,
      url: `/powerbi/embedconfig/${context.reportId}`,
      data: JSON.stringify({ extraDatasets: context.extraDatasets }),
      headers: {'Content-Type': 'application/json'},
      error: (request, error) => {
        console.error(error);
        context.tokenExpiration = null;
      },
      success: async (response) => {
        context.tokenExpiration = response.embed_token.expiration;
        await context.report.setAccessToken(response.embed_token.token)
      }
  });
}

function powerbi_embed_customizeReport($, context, title = '', name = '') {
  const iframes = $(`#${context.selector} iframe`);
  for (let i = 0; i < iframes.length; i++) {
    iframes[i].frameBorder = 0;
    iframes[i].title = title.length > 0 ? title : 'PowerBI Embed';
    iframes[i].name = name.length > 0 ? name : title.length > 0 ? title : 'PowerBI Embed';
  }

  checkTokenAndUpdate($, context);
  setInterval(() => {
      checkTokenAndUpdate($, context);
    },
    INTERVAL_TIME
  );

  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) {
      checkTokenAndUpdate($, context);
    }
  });
}

function updateReportSize($, context) {
  Promise.all([context.report.getActivePage(), context.report.getZoom()])
    .then(([activePage, zoomLevel]) => {
      const { defaultSize } = activePage;
      const zoom = zoomLevel === 0 ? 1 : zoomLevel;
      const scaledWidth = Math.ceil(defaultSize.width * zoom);
      const scaledHeight = Math.ceil(defaultSize.height * zoom);

      $(`#${context.selector}`)
        .css('height', scaledHeight)
        .css('max-width', scaledWidth);
    })
    .catch(error => {
      console.error('Failed to resize report:', error);
    });
}
