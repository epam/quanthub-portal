<?php

namespace Drupal\quanthub_core\Plugin\FullcalendarViewProcessor;

use Drupal\fullcalendar_view\Plugin\FullcalendarViewProcessorBase;

/**
 * Release Type plugin.
 *
 * @FullcalendarViewProcessor(
 *   id = "fullcalendar_view_release_type",
 *   label = @Translation("Release type processor"),
 * )
 */
class ReleaseTypeProcessor extends FullcalendarViewProcessorBase {

  /**
   * Processing view results of fullcalendar_view based on the release type.
   */
  public function process(array &$variables) {
    /** @var \Drupal\views\ViewExecutable $view */
    $view = $variables['view'];
    $view_index = key($variables['#attached']['drupalSettings']['fullCalendarView']);

    $calendar_options = json_decode($variables['#attached']['drupalSettings']['fullCalendarView'][$view_index]['calendar_options'], TRUE);
    // Nothing to do if there are no events to process.
    if (empty($calendar_options['events'])) {
      return;
    }
    $entries = $calendar_options['events'];
    foreach ($view->result as $key => $row) {
      $current_entity = $row->_entity;
      $release_type = $current_entity->get('field_release_type')->value;
      if (!empty($entries[$key])) {
        $entries[$key]['backgroundColor'] = match($release_type) {
          // @todo Set colors from the UI.
          'dataset' => '#a1ecc7',
          'press_release' => '#99cbff',
          'report_submission' => '#ffcda3',
          'announcement' => '#f5d5dc',
          'other' => '#dcd1f9',
          default => '#dcd1f9'
        };
      }
    }
    // Update the entries.
    if ($entries) {
      $calendar_options['events'] = $entries;
      $variables['#attached']['drupalSettings']['fullCalendarView'][$view_index]['calendar_options'] = json_encode($calendar_options);
    }
  }

}
