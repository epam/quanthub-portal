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
    $displayConfig = $entity->field_qh_visualization_transform->value;
    $dafnaType = $entity->field_qh_visualization_type->value;
    $dataFilters = $entity->field_visualization_filters->value;

    $referencedDataset = $entity
      ->get('field_qh_visualization_dataset')
      ->first()
      ->get('entity')
      ->getTarget()
      ->getValue();

    $datasetUrn = $referencedDataset->field_quanthub_urn->value;

    $elements['#attached']['library'][] = 'quanthub_visualization/dafna';

    foreach ($items as $delta => $item) {
      $visualization_data = [
        'visualizationTitle' => $entity->getName(),
        'visualizationConfig' => json_decode($item->value),
        'dataSourceConfig' => json_decode($dataSourceConfig),
        'dataTransformationsConfig' => json_decode($displayConfig),
        'dafnaType' => $dafnaType,
        'dataFilters' => $dataFilters,
        'dataSource' => $datasetUrn,
      ];

      $elements['#attached']['drupalSettings']['quanthubVisualization']['media'][$media_id] = $visualization_data;

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
