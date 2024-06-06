(function($) {
  // Argument passed from InvokeCommand.
  $.fn.quanthubVisualizationPreview = function(argument) {
    window.dispatchEvent(
      new CustomEvent(
        'RERENDER_DAFNA_VISUALIZATION', {
          detail: argument,
        }
      ),
    );
  };
})(jQuery);
