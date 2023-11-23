const INTERVAL_TIME = 30 * 1000;
const TIME_TO_UPDATE = 2 * 60 * 1000;

function applyLocaleBookmark(context) {
  let bookmarks = await context.report.bookmarksManager.getBookmarks();
  bookmarks.forEach(bm => {
    if (bm.displayName === context.report.config.settings.localeSettings.language) {
      await context.report.bookmarksManager.apply(bm.name);
    }
  });
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

function powerbi_embed_customizeReport($, context, width = 0, height = 0, title = '', name = '') {
  const iframes = $(`#${context.selector} iframe`);
  for (let i = 0; i < iframes.length; i++) {
    iframes[i].frameBorder = 0;
    if (iframes[i].attributes.getNamedItem('style')) {
      iframes[i].attributes.removeNamedItem('style');
    }
    iframes[i].title = title.length > 0 ? title : 'PowerBI Embed';
    iframes[i].name = name.length > 0 ? name : title.length > 0 ? title : 'PowerBI Embed';
    const iw = width <= 0 ? '100%' : width + 'px';
    const ih = height <= 0 ? iw * (19 / 32) : height;
    iframes[i].width = iw;
    iframes[i].height = ih + 'px';
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
