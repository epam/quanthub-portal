(function ($, Drupal) {
  Drupal.behaviors.hideAlert = {
    attach: (context, settings) => {
      $('.alert__button').on('click', (event) => {
        $(event.currentTarget).parent('.alert-status').hide();
      })
    }
  };
} (jQuery, Drupal));
