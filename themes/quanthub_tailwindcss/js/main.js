(function ($, Drupal) {
  Drupal.behaviors.hideAlert = {
    attach: (context, settings) => {
      $('.alert__button').on('click', (event) => {
        $(event.currentTarget).parent('.alert-status').hide();
      });
    },
  };
})(jQuery, Drupal);

(function ($, Drupal) {
  $(document.body).append('<div class="modal-backdrop"></div>');
  const backdropEl = $('.modal-backdrop');

  const bodyEl = $('body');
  const headerEl = $('.header');
  const linksEl = $('.header .links');
  const menuLinkEl = $('.mobile-drop-link');

  Drupal.behaviors.handleMenu = {
    attach: () => {
      handleResize();
      window.addEventListener('scroll', () => {
        handleResize();
      });

      window.addEventListener('resize', () => {
        handleSearchClick();
        if (isDesktopView()) {
          bodyEl.removeClass('menu-opened');
        }
      });

      handleLanguageSwitcher();
      handleDropdownclick();
      handlePageOutsideClick();
      handleSearchClick();
      handleMenuMobileClick();
    },
  };

  const handleResize = () => {
    if (window.scrollY > 0) {
      headerEl.addClass('scrolled');
    } else {
      headerEl.removeClass('scrolled');
    }
  };

  const isDesktopView = () => window.innerWidth > 1119;
  const isMaxSearchView = () => window.innerWidth > 1919;

  const addBackdrop = () => {
    if (!backdropEl.hasClass('show')) {
      backdropEl.addClass('show');
    }
  };

  const openMenu = () => {
    if (!bodyEl.hasClass('menu-opened')) {
      addBackdrop();
      bodyEl.addClass('menu-opened');
    }
  };

  const handleLinkClick = (event) => {
    const link = $(event.currentTarget).attr('href');
    window.location.href = link;
  };

  const showDropdownData = (dropdownMenu, dropdown) => {
    dropdownMenu.addClass('show');
    dropdown.addClass('show');
  };

  const showLanguageSwitcher = () => {
    linksEl.addClass('show');
  };

  const hideElement = (...elements) => {
    elements.forEach((el) => {
      el.removeClass('show');
    });
  };

  const hideLanguageSwitcher = () => {
    hideElement(linksEl);
  };

  const hideSearch = () => {
    $('.menu__search').removeClass('show');
    headerEl.removeClass('search-opened');
  };

  const hideMenu = () => {
    bodyEl.removeClass('menu-opened');
  };

  const hideAllElementsByClass = (className) => {
    const allElements = $(className);
    allElements.each(function () {
      if ($(this).hasClass('show')) {
        hideElement($(this));
      }
    });
  };

  const hideAllDropdowns = () => {
    hideAllElementsByClass('.dropdown-menu');
    hideAllElementsByClass('.dropdown');
  };

  const handleLanguageSwitcher = () => {
    $('.header .language-switcher-language-url').off('click');
    $('.header .language-switcher-language-url').on('click', (event) => {
      event.preventDefault();
      openMenu();
      if (isDesktopView()) {
        hideAllDropdowns();
      }
      if (!isDesktopView) {
        hideSearch();
      }
      if (linksEl.hasClass('show')) {
        hideLanguageSwitcher();
        if (isDesktopView()) {
          hideMenu();
        }
        return;
      }
      showLanguageSwitcher();
      $('.language-link').on('click', (linksEvent) => {
        if (!linksEvent.currentTarget.className.includes('is-active')) {
          handleLinkClick(linksEvent);
        }
      });
    });
  };

  const handleDropdownclick = () => {
    $('.header .dropdown').off('click');
    $('.header .dropdown').on('click', (event) => {
      event.preventDefault();
      const currentDropdown = $(event.currentTarget);
      const currentDropdownMenu = currentDropdown.find('.dropdown-menu');
      openMenu();
      if (linksEl.hasClass('show') && isDesktopView()) {
        hideLanguageSwitcher();
        hideSearch();
      }
      if (currentDropdownMenu.hasClass('show')) {
        hideElement(currentDropdown);
        hideElement(currentDropdownMenu);
        if (isDesktopView()) {
          hideMenu();
        }
        return;
      }
      if (isDesktopView()) {
        hideAllDropdowns();
      }
      if (!isDesktopView()) {
        hideSearch();
      }
      showDropdownData(currentDropdownMenu, currentDropdown);
      $(event.currentTarget)
        .find('.dropdown-item')
        .on('click', (itemEvent) => {
          handleLinkClick(itemEvent);
        });
    });
  };

  const handlePageOutsideClick = () => {
    $('.page').off('click');
    $('.page').on('click', (event) => {
      hideAllDropdowns();
      hideLanguageSwitcher();
      hideSearch();
      bodyEl.removeClass('menu-opened');
    });
  };

  const handleSearchClick = () => {
    if (isMaxSearchView()) {
      return;
    }
    const searchMenu = $('.menu__search');
    if (!searchMenu.find('.header--search-icon').length) {
      searchMenu.append('<div class="header--search-icon"></div>');
    }
    const searchIconEl = searchMenu.find('.header--search-icon');
    searchIconEl.off('click');
    searchIconEl.on('click', () => {
      searchMenu.toggleClass('show');
      headerEl.toggleClass('search-opened');
    });
  };

  const handleMenuMobileClick = () => {
    menuLinkEl.off('click');
    menuLinkEl.on('click', (event) => {
      if (bodyEl.hasClass('menu-opened')) {
        bodyEl.removeClass('menu-opened');
        hideLanguageSwitcher();
        hideAllDropdowns();
        return;
      }
      bodyEl.addClass('menu-opened');
    });
  };
})(jQuery, Drupal);

(function ($, Drupal) {
  Drupal.behaviors.handleTopicsDropdown = {
    attach: () => {
      handleTopicsDropdown();
    },
  };

  const handleTopicsDropdown = () => {
    $(document).ready(function () {
      const allTopicsContent = $('.topics--tree .view-grouping .view-grouping-content');
      allTopicsContent.each((index) => {
        const topicContent = $(allTopicsContent[index]);
        const topicHeader = $(topicContent.find('h3'));
        topicHeader.on('click', () => {
          topicContent.hasClass('show') ? topicContent.removeClass('show') : topicContent.addClass('show');
        });
      });
    });
  };
})(jQuery, Drupal);
