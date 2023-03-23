<?php

namespace Drupal\quanthub_sdmx_proxy\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for quanthub SDMX proxy.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->setConfigFactory($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['quanthub_sdmx_proxy.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'quanthub_sdmx_proxy_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('quanthub_sdmx_proxy.settings');

    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API URL'),
      '#default_value' => $settings->get('api_url'),
    ];

    $form['auth_token_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auth token endpoint'),
      '#default_value' => $settings->get('auth_token_endpoint'),
    ];

    $form['auth_client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auth Client Id'),
      '#default_value' => $settings->get('auth_client_id'),
    ];

    $form['auth_client_secret'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Auth Client Secret'),
      '#default_value' => $settings->get('auth_client_secret'),
    ];

    $form['auth_scope'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auth Scope'),
      '#default_value' => $settings->get('auth_scope'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('quanthub_sdmx_proxy.settings')
      ->set('api_url', $form_state->getValue('api_url'))
      ->set('auth_token_endpoint', $form_state->getValue('auth_token_endpoint'))
      ->set('auth_client_id', $form_state->getValue('auth_client_id'))
      ->set('auth_client_secret', $form_state->getValue('auth_client_secret'))
      ->set('auth_scope', $form_state->getValue('auth_scope'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
