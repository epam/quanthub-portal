<?php

namespace Drupal\quanthub_core\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\NumberWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'number' widget for scaling.
 *
 * @FieldWidget(
 *   id = "quanthub_scale_number",
 *   label = @Translation("Quanthub Scale Number field"),
 *   field_types = {
 *     "integer",
 *     "decimal",
 *     "float"
 *   }
 * )
 */
class QuantHubScaleNumberWidget extends NumberWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['value']['#type'] = 'select';
    $element['value']['#default_value'] = (integer) $element['value']['#default_value'];
    $element['value']['#options'] = [
      100 => $this->t('Hundreds'),
      1000 => $this->t('Thousands'),
      1000000 => $this->t('Millions'),
      1000000000 => $this->t('Billions'),
      1000000000000 => $this->t('Trillions'),
    ];
    $element['value']['#empty_option'] = $this->t('No scaling');

    return $element;
  }

}
