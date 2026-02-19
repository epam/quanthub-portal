<?php

namespace Drupal\quanthub_visualization\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multivalue_form_element\Element\MultiValue;

/**
 * Implementation of the 'quanthub_visualization_transformation_json' widget.
 *
 * @todo refactor and remove.
 *
 * @FieldWidget(
 *   id = "quanthub_visualization_transformation_json",
 *   label = @Translation("Quanthub Visualization Data Transformation Widget"),
 *   field_types = {
 *     "json",
 *     "json_native",
 *     "json_native_binary"
 *   }
 * )
 */
class QuanthubVisualizationTransformationWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $json_value = json_decode($items->getString(), 1);

    // @todo add remove button.
    $element['value'] = [
      '#type' => 'multivalue',
      '#title' => $this->t('Data Transformation Config'),
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#default_value' => !empty($json_value['display']) ? $json_value['display'] : [],
      'dimensionId' => [
        '#type' => 'textfield',
        '#title' => $this->t('Dimension ID'),
      ],
      'field' => [
        '#type' => 'textfield',
        '#title' => $this->t('Field'),
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
      if (empty($item['dimensionId']) && empty($item['field'])) {
        return FALSE;
      }
      else {
        return TRUE;
      }
    });

    $value = json_encode(['display' => $value]);
    $form_state->setValueForElement($element, $value);
  }

}
