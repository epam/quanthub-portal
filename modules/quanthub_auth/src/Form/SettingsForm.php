<?php

namespace Drupal\quanthub_auth\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\quanthub_auth\QuanthubAuthInterface;
use Drupal\quanthub_auth\QuanthubAuthPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Quanthub Authentication settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    protected TypedConfigManagerInterface $typedConfigManager,
    protected QuanthubAuthInterface $quanthubAuth,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('quanthub.auth'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'quanthub_auth_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $definitions = $this->quanthubAuth->getDefinitions();

    $form[QuanthubAuthPluginInterface::AUTHENTICATED] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Authenticated user token sources'),
    ];

    $form[QuanthubAuthPluginInterface::ANONYMOUS] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Anonymous user token sources'),
    ];

    foreach ($definitions as $plugin_id => $definition) {
      if (!isset($form[$definition['scope']])) {
        continue;
      }
      $plugin = $this->quanthubAuth->createInstance($plugin_id, ['form' => TRUE]);

      $form[$definition['scope']][$plugin_id] = [
        '#type' => 'details',
        '#title' => $definition['label'],
        '#open' => TRUE,
        '#tree' => TRUE,
      ];

      $form[$definition['scope']][$plugin_id]['message'] = [
        '#markup' => $plugin->getMessage(),
        '#theme_wrappers' => ['fieldset'],
      ];

      if ($plugin instanceof PluginFormInterface) {
        $subform_state = SubformState::createForSubform($form[$definition['scope']][$plugin_id], $form, $form_state);
        $form[$definition['scope']][$plugin_id] = $plugin->buildConfigurationForm($form[$definition['scope']][$plugin_id], $subform_state);
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    foreach ($this->quanthubAuth->getDefinitions() as $plugin_id => $definition) {
      if (is_subclass_of($definition['class'], PluginFormInterface::class)) {
        $plugin = $this->quanthubAuth->createInstance($plugin_id, ['form' => TRUE]);
        $subform_state = SubformState::createForSubform($form[$definition['scope']][$plugin_id], $form, $form_state);
        $plugin->validateConfigurationForm($form[$definition['scope']][$plugin_id], $subform_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    foreach ($this->quanthubAuth->getDefinitions() as $plugin_id => $definition) {
      if (is_subclass_of($definition['class'], PluginFormInterface::class)) {
        $plugin = $this->quanthubAuth->createInstance($plugin_id, ['form' => TRUE]);
        $subform_state = SubformState::createForSubform($form[$definition['scope']][$plugin_id], $form, $form_state);
        $plugin->submitConfigurationForm($form[$definition['scope']][$plugin_id], $subform_state);
      }
    }
  }

}
