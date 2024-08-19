<?php

namespace Drupal\quanthub_visualization\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multivalue_form_element\Element\MultiValue;

/**
 * Plugin implementation of the 'quanthub_visualization_filters_json' widget.
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

    // @todo add remove button.
    $element['value'] = [
      '#type' => 'multivalue',
      '#title' => $this->t('Time Filters'),
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#default_value' => !empty($json_value['filters']) ? $json_value['filters'] : [],
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

    return $element;
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
