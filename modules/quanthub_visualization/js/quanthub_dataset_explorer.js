(function($) {
  // Argument passed from InvokeCommand.
  $.fn.quanthubDatasetExplorerPreview = function(argument) {
    window.dispatchEvent(
      new CustomEvent(
        'RERENDER_DATA_PREVIEW', {
          detail: argument,
        }
      ),
    );
  };
})(jQuery);

