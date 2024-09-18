<?php

namespace Drupal\quanthub_visualization\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Plugin implementation of the 'quanthub_visualization_json' widget.
 *
 * @FieldWidget(
 *   id = "quanthub_visualization_json",
 *   label = @Translation("Quanthub Visualization Widget"),
 *   field_types = {
 *     "json",
 *     "json_native",
 *     "json_native_binary"
 *   }
 * )
 */
class QuanthubVisualizationWidget extends StringTextareaWidget
{

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
  {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#attached']['library'][] = 'quanthub_visualization/dafna-config-editor';
    $element['#attached']['library'][] = 'quanthub_visualization/dafna';
    $element['#attached']['library'][] = 'quanthub_visualization/dafna_rebuild';

    $element['#attached']['library'][] = 'quanthub_visualization/quanthub-dataset-rebuild';
    $element['#attached']['library'][] = 'quanthub_visualization/quanthub-dataset-explorer';

    $element['value']['#type'] = 'hidden';
    $element['title'] = [
      '#type' => 'item',
      '#title' => $this->t('Visualization Config'),
    ];
    $element['qh_json_editor'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['qh-json-editor-container'],
      ],
    ];

    $element['qh_visulaization_preview'] = [
      '#type' => 'button',
      '#value' => $this->t('Preview Visualization'),
      '#ajax' => [
        'callback' => [$this, 'preview'],
        'wrapper' => 'previewWrapper',
      ],
      '#attributes' => [
        'class' => ['qh-visualization-preview-button'],
      ],
      '#button_type' => 'default',
    ];

    $element['qh_datasetexplorer_preview'] = [
      '#type' => 'button',
      '#value' => $this->t('Preview Dataset'),
      '#name' => 'datasetexplorer',
      '#ajax' => [
        'callback' => [$this, 'datasetExplorerPreview'],
        'wrapper' => 'previewWrapper',
      ],
      '#attributes' => [
        'class' => ['qh-datasetexplorer-preview-button'],
      ],
      '#button_type' => 'default',
    ];

    $element['qh_visualization_preview_container'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['visualization-preview-container'],
      ],
    ];

    $element['qh_datasetexplorer_preview_container'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['datasetexplorer-preview-container'],
      ],
    ];

    return $element;
  }

  /**
   * Ajax callback for preparing visualization data for preview.
   */
  public function preview(array &$form, FormStateInterface $form_state): AjaxResponse
  {
    $datasetUrn = Node::load($form_state->getValue('field_qh_visualization_dataset')[0]['target_id'])->get('field_quanthub_urn')->getString();
//    $form_state->getFormObject()->media->get('field_qh_visualization_dataset')->get(0)->getValue()['target_id']

//    $datasetUrn = $form_state->getFormObject()->media->get('field_qh_visualization_dataset')->get(0)->get('target_id')->getValue();
    $filters = $form_state->getValue('field_qh_visualization_filters')[0]['value'];
//    $filters = reset($form_state->getValue('media'))['fields']['field_qh_visualization_filters'][0]['value'];
    unset($filters['add_more']);
    foreach ($filters as &$filter) {
      unset($filter['_weight']);
    }

    $filters = array_filter($filters, function ($item) {
      if (empty($item['componentCode']) && empty($item['operator']) && empty($item['value'])) {
        return FALSE;
      } else {
        return TRUE;
      }
    });
//    $displayConfig = reset($form_state->getValue('media'))['fields']['field_qh_visualization_transform'][0]['value'];
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
//    $visualizationConfig = reset($form_state->getValue('media'))['fields']['field_media_qh_visualization'][0]['value'];
//    $data = \Drupal::service('quanthub_visualization.media_config')->extractMediaConfig($form_state);
    $visualizationConfig = $form_state->getValue('field_media_qh_visualization')[0]['value'];
    $dafnaType = $form_state->getValue('field_qh_visualization_type')[0]['value'];
    $dataFilters = $form_state->getValue('field_visualization_filters')[0]['value'];
    $preview_data['#attached']['drupalSettings']['workspaceId'] = getenv('SDMX_WORKSPACE_ID');
    $preview_data['#attached']['drupalSettings']['quanthubVisualization']['media'][0] = [
      'dafnaType' => $dafnaType,
      'dataFilters' => empty($dataFilters) ? null : $dataFilters,
      'visualizationTitle' => $form_state->getValue('name')[0]['value'],
      'visualizationConfig' => json_decode($visualizationConfig),
      'dataTransformationsConfig' => $dataTransformationsConfig,
      'dataSourceConfig' => ['timeFilters' => $filters], //filters
      'dataSource' => $datasetUrn,
    ];

    $preview_data['qh_visualization_preview'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => 'vega-visualization',
        'data-media-id' => 0,
      ],
    ];

    $response = new AjaxResponse();

    $response->addCommand(new HtmlCommand('.visualization-preview-container', $preview_data, []));
    $response->addCommand(new InvokeCommand('html', 'quanthubVisualizationPreview', [0]));

    return $response;
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return AjaxResponse
   */
  public function datasetExplorerPreview(array &$form, FormStateInterface $form_state): AjaxResponse
  {
    $datasetUrn = Node::load($form_state->getValue('field_qh_visualization_dataset')[0]['target_id'])->get('field_quanthub_urn')->getString();
//    $form_state->getFormObject()->media->get('field_qh_visualization_dataset')->get(0)->getValue()['target_id']

//    $datasetUrn = $form_state->getFormObject()->media->get('field_qh_visualization_dataset')->get(0)->get('target_id')->getValue();
    $filters = $form_state->getValue('field_qh_visualization_filters')[0]['value'];
//    $filters = reset($form_state->getValue('media'))['fields']['field_qh_visualization_filters'][0]['value'];
    unset($filters['add_more']);
    foreach ($filters as &$filter) {
      unset($filter['_weight']);
    }

    $filters = array_filter($filters, function ($item) {
      if (empty($item['componentCode']) && empty($item['operator']) && empty($item['value'])) {
        return FALSE;
      } else {
        return TRUE;
      }
    });
//    $displayConfig = reset($form_state->getValue('media'))['fields']['field_qh_visualization_transform'][0]['value'];
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
//    $visualizationConfig = reset($form_state->getValue('media'))['fields']['field_media_qh_visualization'][0]['value'];
//    $data = \Drupal::service('quanthub_visualization.media_config')->extractMediaConfig($form_state);
    $visualizationConfig = $form_state->getValue('field_media_qh_visualization')[0]['value'];
    $dafnaType = $form_state->getValue('field_qh_visualization_type')[0]['value'];
    $dataFilters = $form_state->getValue('field_visualization_filters')[0]['value'];
    $preview_data['#attached']['drupalSettings']['workspaceId'] = getenv('SDMX_WORKSPACE_ID');
    $preview_data['#attached']['drupalSettings']['quanthubVisualization']['media'][0] = [
      'dafnaType' => $dafnaType,
      'dataFilters' => empty($dataFilters) ? null : $dataFilters,
      'visualizationTitle' => $form_state->getValue('name')[0]['value'],
      'visualizationConfig' => json_decode($visualizationConfig),
      'dataTransformationsConfig' => $dataTransformationsConfig,
      'dataSourceConfig' => ['timeFilters' => $filters], //filters
      'dataSource' => $datasetUrn,
    ];

    $preview_data['qh_datasetexplorer_preview'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => 'dataset_explorer_preview dataset_explorer',
        'id' => 'dataset_explorer_preview dataset_explorer',
        'data-media-id' => 0,
      ],
    ];

    $response = new AjaxResponse();

    $response->addCommand(new HtmlCommand('.datasetexplorer-preview-container', $preview_data, []));
    $response->addCommand(new InvokeCommand('html', 'quanthubDatasetExplorerPreview', [0]));
    return $response;
  }

}
