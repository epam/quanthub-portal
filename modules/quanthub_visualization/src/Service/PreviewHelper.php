<?php

namespace Drupal\quanthub_visualization\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Visualization preview helper service.
 */
class PreviewHelper {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * Collect all necessary data for previews.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   Prepared form.
   */
  public function provideDrupalSettingsData(FormStateInterface $form_state) {
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    // Support "field_qh_visualization_dataset" for backward compatibility.
    $datasetField = $form_state->getValue('field_dataset')[0]['target_id']
      ?? $form_state->getValue('field_media_qh_visualization')[0]['target_id'];
    $datasetUrn = $nodeStorage->load($datasetField)->get('field_quanthub_urn')->getString();

    $filters = $form_state->getValue('field_qh_visualization_filters')[0]['value'];

    unset($filters['add_more']);
    foreach ($filters as &$filter) {
      unset($filter['_weight']);
    }

    $filters = array_filter($filters, function ($item) {
      if (empty($item['componentCode']) && empty($item['operator']) && empty($item['value'])) {
        return FALSE;
      }
      else {
        return TRUE;
      }
    });

    $displayConfig = $form_state->getValue('field_qh_visualization_transform')[0]['value'];
    $dataTransformationsConfig['display'] = [];

    // Build dataTransformationsConfig.
    foreach ($displayConfig as $item) {
      if (is_array($item) && !empty($item['dimensionId'])) {
        $dataTransformationsConfig['display'][] = [
          'dimensionId' => $item['dimensionId'],
          'field' => $item['field'],
        ];
      }
    }

    $visualizationConfig = $form_state->getValue('field_media_qh_visualization')[0]['value'];
    $dafnaType = $form_state->getValue('field_qh_visualization_type')[0]['value'];
    $dataFilters = $form_state->getValue('field_visualization_filters')[0]['value'];

    $preview_data['#attached']['drupalSettings']['quanthubVisualization']['media'][0] = [
      'dafnaType' => $dafnaType,
      'dataFilters' => empty($dataFilters) ? NULL : $dataFilters,
      'visualizationTitle' => $form_state->getValue('name')[0]['value'],
      'visualizationConfig' => json_decode($visualizationConfig),
      'dataTransformationsConfig' => $dataTransformationsConfig,
      'dataSourceConfig' => ['timeFilters' => $filters],
      'dataSource' => $datasetUrn,
    ];

    return $preview_data;
  }

}
