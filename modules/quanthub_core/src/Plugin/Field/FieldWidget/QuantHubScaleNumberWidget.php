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
    $element['value']['#options'] = [
      10 => '10',
      100 => '100',
      1000 => '1 000',
      1000000 => '1 000 000',
      1000000000 => '1 000 000 000',
    ];

    return $element;
  }

}
