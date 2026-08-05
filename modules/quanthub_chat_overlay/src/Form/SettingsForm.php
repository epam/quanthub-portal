<?php

namespace Drupal\quanthub_chat_overlay\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * General settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['quanthub_chat_overlay.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'quanthub_chat_overlay_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('quanthub_chat_overlay.settings');
    $overridden = $this->configFactory()->get('quanthub_chat_overlay.settings');
    $versions = [
      'v1' => '@epam/ai-dial-overlay (deprecated)',
      'v2' => '@epam/ai-dial-chat-overlay',
    ];

    $form['api'] = [
      '#type' => 'radios',
      '#title' => $this->t('DIAL Chat Overlay library version'),
      '#default_value' => $config->get('api'),
      '#required' => TRUE,
      '#options' => $versions,
      'v1' => [
        '#description' => $this->t('Attaches automatically to pages.'),
      ],
      'v2' => [
        '#description' => $this->t('Attaches with "AI Dial Chat Overlay" block.'),
      ],
    ];

    $form['chat_url'] = [
      '#type' => 'url',
      '#title' => $this->t('DIAL Chat URL'),
      '#default_value' => $config->get('chat_url'),
    ];

    $form['model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DIAL Default Model'),
      '#default_value' => $config->get('model'),
    ];

    $form['auth_provider'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DIAL Sing-in Provider'),
      '#default_value' => $config->get('auth_provider'),
    ];

    $form['features'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enabled Features'),
      '#default_value' => $config->get('features'),
    ];

    foreach (['chat_url', 'model', 'auth_provider', 'features'] as $key) {
      $value = $overridden->get($key);
      if ($config->get($key) !== $value) {
        $form[$key]['#description'][]['#markup'] = $this->t('Overridden with: <strong>@value</strong>', [
          '@value' => $form[$key]['#type'] === 'password' ? substr($value, 0, 8) . '******' : $value,
        ]);
        foreach ($form[$key]['#description'] as &$element) {
          $element['#theme_wrappers'] = ['container'];
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('quanthub_chat_overlay.settings')
      ->set('api', $form_state->getValue('api'))
      ->set('chat_url', $form_state->getValue('chat_url'))
      ->set('model', $form_state->getValue('model'))
      ->set('auth_provider', $form_state->getValue('auth_provider'))
      ->set('features', $form_state->getValue('features'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
