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
class QuanthubVisualizationWidget extends StringTextareaWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#attached']['library'][] = 'quanthub_visualization/dafna-config-editor';
    $element['#attached']['library'][] = 'quanthub_visualization/dafna';
    $element['#attached']['library'][] = 'quanthub_visualization/dafna_rebuild';

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

    $element['qh_visualization_preview_container'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['visualization-preview-container'],
      ],
    ];

    return $element;
  }

  /**
   * Ajax callback for preparing visualization data for preview.
   */
  public function preview(array &$form, FormStateInterface $form_state): AjaxResponse {
    $datasetUrn = Node::load($form_state->getValue('field_qh_visualization_dataset')[0]['target_id'])->get('field_quanthub_urn')->getString();

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

    $preview_data['#attached']['drupalSettings']['quanthubVisualization']['media'][0] = [
      'visualizationTitle' => $form_state->getValue('name')[0]['value'],
      'visualizationConfig' => json_decode($form_state->getValue('field_media_qh_visualization')[0]['value']),
      'dataSourceConfig' => ['filters' => $filters],
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

}
