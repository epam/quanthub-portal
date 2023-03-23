<?php

namespace Drupal\quanthub_core\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\powerbi_embed\Plugin\Field\FieldWidget\PowerBIEmbedWidget;

/**
 * Extending the contrib PowerBIEmbedWidget.
 *
 * @FieldWidget(
 *   id = "quanthub_powerbi_embed_widget",
 *   label = @Translation("Quanthub PowerBI Embed report reference"),
 *   description = @Translation("Use to reference PowerBI Embed report"),
 *   field_types = {
 *     "quanthub_powerbi_embed",
 *   }
 * )
 */
class QuantHubPowerBIEmbedWidget extends PowerBIEmbedWidget {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['report_extra_datasets'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Report extra datasets'),
      '#description' => $this->t('PowerBI Report extra datasets'),
      '#default_value' => $items[$delta]->report_extra_datasets ?? NULL,
      '#size' => 255,
    ];

    $element['report_page'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Report Page'),
      '#description' => $this->t('PowerBI Report page'),
      '#default_value' => $items[$delta]->report_page ?? NULL,
      '#size' => 255,
    ];

    $element['report_visual'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Report Visual'),
      '#description' => $this->t('PowerBI Report visual'),
      '#default_value' => $items[$delta]->report_visual ?? NULL,
      '#size' => 255,
    ];

    return $element;
  }

}
