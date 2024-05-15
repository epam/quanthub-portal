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

    $entity = $items->getEntity();
    $media_id = $entity->id();
    $dataSourceConfig = $entity->field_qh_visualization_filters->value;

    $referencedDataset = $entity
      ->get('field_qh_visualization_dataset')
      ->first()
      ->get('entity')
      ->getTarget()
      ->getValue();

    $datasetUrn = $referencedDataset->field_quanthub_urn->value;

    $elements['#attached']['library'][] = 'quanthub_visualization/dafna';

    foreach ($items as $delta => $item) {
      $elements['#attached']['drupalSettings']['quanthubVisualization']['media'][$media_id]['visualizationConfig'] = json_decode($item->value);
      $elements['#attached']['drupalSettings']['quanthubVisualization']['media'][$media_id]['dataSourceConfig'] = json_decode($dataSourceConfig);
      $elements['#attached']['drupalSettings']['quanthubVisualization']['media'][$media_id]['dataSource'] = $datasetUrn;

      $elements[$delta] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => 'vega-visualization',
            'data-media-id' => $media_id,
          ],
        ],
      ];
    }

    return $elements;
  }

}
