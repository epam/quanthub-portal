<?php

namespace Drupal\quanthub_visualization\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Quanthub Visualization formatter.
 *
 * @FieldFormatter(
 *   id = "quanthub_visualization",
 *   label = @Translation("Quanthub Visualization Formatter"),
 *   field_types = {
 *     "json",
 *     "json_native",
 *     "json_native_binary",
 *   }
 * )
 */
class QuanthubVisualizationFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    $media_id = $items->getEntity()->id();

    $elements['#attached']['library'][] = 'quanthub_visualization/dafna';

    foreach ($items as $delta => $item) {
      $elements['#attached']['drupalSettings']['quanthubVisualization']['media'][$media_id] = json_decode($item->value);

      $elements[$delta] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => 'vega-visualization',
            'data-media-id' => $media_id
          ],
        ],
      ];
    }

    return $elements;
  }

}
