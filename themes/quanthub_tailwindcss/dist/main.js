!function(e,n){n.behaviors.hideAlert={attach:function(n,o){e(".alert__button").on("click",(function(n){e(n.currentTarget).parent(".alert-status").hide()}))}}}(jQuery,Drupal),function(e,n){e(document.body).append('<div class="modal-backdrop"></div>');var o=e(".modal-backdrop"),a=e("body"),s=e(".header"),i=e(".header .links"),c=e(".mobile-drop-link");n.behaviors.handleMenu={attach:function(){r(),window.addEventListener("scroll",(function(){r()})),window.addEventListener("resize",(function(){C(),t()&&a.removeClass("menu-opened")})),m(),p(),k(),C(),b()}};var r=function(){window.scrollY>0?s.addClass("scrolled"):s.removeClass("scrolled")},t=function(){return window.innerWidth>1119},l=function(){a.hasClass("menu-opened")||(o.hasClass("show")||o.addClass("show"),a.addClass("menu-opened"))},d=function(n){var o=e(n.currentTarget).attr("href");window.location.href=o},u=function(){for(var e=arguments.length,n=new Array(e),o=0;o<e;o++)n[o]=arguments[o];n.forEach((function(e){e.removeClass("show")}))},f=function(){u(i)},h=function(){e(".menu__search").removeClass("show"),s.removeClass("search-opened")},w=function(){a.removeClass("menu-opened")},v=function(n){e(n).each((function(){e(this).hasClass("show")&&u(e(this))}))},g=function(){v(".dropdown-menu"),v(".dropdown")},m=function(){e(".header .language-switcher-language-url").off("click"),e(".header .language-switcher-language-url").on("click",(function(n){if(n.preventDefault(),l(),t()&&g(),t||h(),i.hasClass("show"))return f(),void(t()&&w());i.addClass("show"),e(".language-link").on("click",(function(e){e.currentTarget.className.includes("is-active")||d(e)}))}))},p=function(){e(".header .dropdown").off("click"),e(".header .dropdown").on("click",(function(n){n.preventDefault();var o,a=e(n.currentTarget),s=a.find(".dropdown-menu");if(l(),i.hasClass("show")&&t()&&(f(),h()),s.hasClass("show"))return u(a),u(s),void(t()&&w());t()&&g(),t()||h(),o=a,s.addClass("show"),o.addClass("show"),e(n.currentTarget).find(".dropdown-item").on("click",(function(e){d(e)}))}))},k=function(){e(".page").off("click"),e(".page").on("click",(function(e){g(),f(),h(),a.removeClass("menu-opened")}))},C=function(){if(!(window.innerWidth>1919)){var n=e(".menu__search");n.find(".header--search-icon").length||n.append('<div class="header--search-icon"></div>');var o=n.find(".header--search-icon");o.off("click"),o.on("click",(function(){n.toggleClass("show"),s.toggleClass("search-opened")}))}},b=function(){c.off("click"),c.on("click",(function(e){if(a.hasClass("menu-opened"))return a.removeClass("menu-opened"),f(),void g();a.addClass("menu-opened")}))}}(jQuery,Drupal),function(e,n){var o=e(".block-views-blockgauges-home-gauges"),a=e(".block-views-blockgauges-home-gauges .view-gauges"),s=e(".block-views-blockgauges-home-gauges .view-content"),i=e(".gauges-arrows--back"),c=e(".gauges-arrows--forward"),r=e(".gauges-arrows");n.behaviors.handleFactoidsBehaviour={attach:function(){o.length&&(t(),e(window).resize((function(){t()})))}};var t=function(){var n=s.scrollLeft();l(n,i,c,h);var t=a.width(),d=function(){var n=e(".block-views-blockgauges-home-gauges .view-content .views-row").length,o=e(e(".block-views-blockgauges-home-gauges .view-content .views-row")[0]).outerWidth()+80;return{scrollStep:o,contentWidth:n*o-80}}(),u=d.scrollStep,f=d.contentWidth,h=f-t;if(o.addClass("scrollable"),t>f||window.innerWidth<720)return o.removeClass("scrollable"),void r.hide();s.off("scroll"),s.on("scroll",(function(){l(s.scrollLeft(),i,c,h)})),r.show(),c.off("click"),c.on("click",(function(){n+u<h?n+=u:n=h,s.animate({scrollLeft:n},300)})),i.off("click"),i.on("click",(function(){n>u?n-=u:n=0,s.animate({scrollLeft:n},300)}))},l=function(e,n,o,a){o.removeClass("disabled"),n.removeClass("disabled"),e===a&&o.addClass("disabled"),0===e&&n.addClass("disabled")}}(jQuery,Drupal),function(e,n){n.behaviors.handleFooterDropdown={attach:function(){o()}};var o=function(){e(document).ready((function(){var n=e(".topics--tree .view-grouping .view-grouping-content");n.each((function(o){var a=e(n[o]);e(a.find("h3")).on("click",(function(){a.hasClass("show")?a.removeClass("show"):a.addClass("show")}))}))}))}}(jQuery,Drupal);