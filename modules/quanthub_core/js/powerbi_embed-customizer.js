function powerbi_embed_customizeReportEmbed(sel, width = 0, height = 0, title = '', name = '') {
  // Override iframe sizing on report rendered in div. Other iframe properties may be customized here.
  window.addEventListener('DOMContentLoaded', () => {
    const iframes = document.querySelectorAll(`#${sel} iframe`);
    for (let i = 0; i < iframes.length; i++) {
      iframes[i].frameBorder = 0;
      iframes[i].attributes.removeNamedItem('style');
      iframes[i].title = title.length > 0 ? title : 'PowerBI Embed';
      iframes[i].name = name.length > 0 ? name : title.length > 0 ? title : 'PowerBI Embed';
      const iw = width <= 0 ? '100%' : width + 'px';
      const ih = height <= 0 ? iw * (19 / 32) : height;
      iframes[i].width = iw;
      iframes[i].height = ih + 'px';
    }
  });
}
