<?php

namespace Drupal\quanthub_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\text\Plugin\Field\FieldFormatter\TextDefaultFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'key_value' formatter.
 *
 * @FieldFormatter(
 *   id = "quanthub_key_value",
 *   label = @Translation("Quanthub Key Value"),
 *   field_types = {
 *     "key_value",
 *     "key_value_long",
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class QuanthubKeyValueFormatter extends TextDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['value_only'] = FALSE;
    $settings['show_period'] = FALSE;
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    // Get the default textfield form.
    $form = parent::settingsForm($form, $form_state);
    // Allow the formatter to hide the key.
    $form['value_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Value only'),
      '#default_value' => $this->getSetting('value_only'),
      '#description' => $this->t('Make the formatter hide the "Key" part of the field and display the "Value" only.'),
      '#weight' => 4,
    ];
    $form['show_period'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show period'),
      '#default_value' => $this->getSetting('show_period'),
      '#description' => $this->t('Show period (Key: Value)'),
      '#weight' => 5,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Get the value elements from the TextDefaultFormatter class.
    $value_elements = parent::viewElements($items, $langcode);

    // Buffer the return value.
    $elements = [];

    // Loop through all items.
    foreach ($items as $delta => $item) {
      // Just add the key element to the render array, when 'value_only' is not
      // checked.
      if (isset($item->key)) {
        if (!$this->getSetting('value_only') && $this->getSetting('show_period')) {
          $elements[$delta]['key'] = [
            '#plain_text' => nl2br($item->key . ': '),
          ];
        }
        if (!$this->getSetting('value_only') && !$this->getSetting('show_period')) {
          $elements[$delta]['key'] = [
            '#plain_text' => nl2br($item->key),
          ];
        }
      }
      // Add the value to the render array.
      $elements[$delta]['value'] = $value_elements[$delta];
    }
    return $elements;
  }

}
