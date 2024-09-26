<?php

namespace Drupal\quanthub_visualization\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\quanthub_visualization\Service\PreviewHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * {@inheritDoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected PreviewHelper $previewHelperService,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('quanthub_visualization.preview_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#attached']['library'][] = 'quanthub_visualization/dafna-config-editor';
    $element['#attached']['library'][] = 'quanthub_visualization/dafna';
    $element['#attached']['library'][] = 'quanthub_visualization/dafna_rebuild';

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

    $element['qh_visualization_preview_container'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['visualization-preview-container'],
      ],
    ];

    // WorkspaceID always relevant.
    $element['#attached']['drupalSettings']['workspaceId'] = getenv('SDMX_WORKSPACE_ID');
    return $element;
  }

  /**
   * Ajax callback for preparing visualization data for preview.
   */
  public function preview(array &$form, FormStateInterface $form_state): AjaxResponse {
    $preview_data = $this->previewHelperService->provideDrupalSettingsData($form_state);

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
