<?php

namespace Drupal\quanthub_visualization\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multivalue_form_element\Element\MultiValue;
use Drupal\quanthub_visualization\Service\PreviewHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'quanthub_visualization_filters_json' widget.
 *
 * @todo refactor and remove.
 *
 * @FieldWidget(
 *   id = "quanthub_visualization_filters_json",
 *   label = @Translation("Quanthub Visualization Filters Widget"),
 *   field_types = {
 *     "json",
 *     "json_native",
 *     "json_native_binary"
 *   }
 * )
 */
class QuanthubVisualizationFiltersWidget extends WidgetBase {

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
   * Options for operator.
   */
  const OPTIONS = [
    'EQUALS' => 'eq',
    'NOT_EQUALS' => 'ne',
    'LESS' => 'lt',
    'LESS_OR_EQUAL' => 'le',
    'GREATER' => 'gt',
    'GREATER_OR_EQUAL' => 'ge',
    'CONTAINS' => 'co',
    'NOT_CONTAINS' => 'nc',
    'STARTS' => 'sw',
    'ENDS' => 'ew',
  ];

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $json_value = json_decode($items->getString(), 1);

    $element['#attached']['library'][] = 'quanthub_visualization/quanthub-dataset-rebuild';
    $element['#attached']['library'][] = 'quanthub_visualization/quanthub-dataset-explorer';

    // @todo add remove button.
    $element['value'] = [
      '#type' => 'multivalue',
      '#title' => $this->t('Time Filters'),
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#default_value' => !empty($json_value['timeFilters']) ? $json_value['timeFilters'] : [],
      'operator' => [
        '#type' => 'select',
        '#title' => $this->t('Operator'),
        '#options' => array_flip(self::OPTIONS),
        '#empty_option' => $this->t('- None -'),
      ],
      'value' => [
        '#type' => 'textfield',
        '#title' => $this->t('Value'),
      ],
      '#element_validate' => [
        [$this, 'validate'],
      ],
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

    $element['qh_datasetexplorer_preview_container'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['datasetexplorer-preview-container'],
      ],
    ];
    // WorkspaceID always relevant.
    $element['#attached']['drupalSettings']['workspaceId'] = getenv('SDMX_WORKSPACE_ID');
    return $element;
  }

  /**
   * Dataset explorer preview.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function datasetExplorerPreview(array &$form, FormStateInterface $form_state): AjaxResponse {
    $preview_data = $this->previewHelperService->provideDrupalSettingsData($form_state);

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

  /**
   * Validation and setting value in widget.
   *
   * @param array $element
   *   The render array of element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form_state object.
   */
  public function validate($element, FormStateInterface $form_state) {
    $value = $element['#value'];

    $value = array_filter($value, function ($item) {
      if (empty($item['operator']) && empty($item['value'])) {
        return FALSE;
      }
      else {
        return TRUE;
      }
    });

    $value = json_encode(['timeFilters' => $value]);
    $form_state->setValueForElement($element, $value);
  }

}
