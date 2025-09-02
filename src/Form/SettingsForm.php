<?php

namespace Drupal\quanthub\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Quanthub settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'quanthub_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['quanthub.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('quanthub.settings');

    $form['content'] = [
      '#type' => 'details',
      '#title' => $this->t('Content settings'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['content']['import'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Import demo content'),
      '#default_value' => $config->get('content.import'),
      '#description' => $this->t('If checked demo content would be imported with Default Content module install.'),
    ];

    $form['content']['title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow node title configuration in view modes'),
      '#default_value' => $config->get('content.title'),
      '#description' => $this->t('If checked node title variable in Twig templates would be empty.'),
    ];

    $form['content']['urn_versioning'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow version in URN fields'),
      '#default_value' => $config->get('content.urn_versioning'),
      '#description' => $this->t('If unchecked the latest version ("~") is allowed only in URN string "AGENCY:ID(~)".'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('quanthub.settings')
      ->set('content', $form_state->getValue('content', []))
      ->save();

    self::clearCaches();

    parent::submitForm($form, $form_state);
  }

  /**
   * Clears all caches affected by Quanthub configuration.
   */
  public static function clearCaches(): void {
    \Drupal::entityTypeManager()->getViewBuilder('node')->resetCache();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    \Drupal::cache('render')->deleteAll();
  }

}
