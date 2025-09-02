<?php

namespace Drupal\quanthub_auth\Plugin\QuanthubAuth;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\quanthub_auth\QuanthubAuthPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides static token from key.
 *
 * @QuanthubAuth(
 *   id = "key",
 *   scope = \Drupal\quanthub_auth\QuanthubAuthPluginInterface::ANONYMOUS,
 *   label = @Translation("Static token from key"),
 *   priority = -100
 * )
 */
class Key extends PluginBase implements QuanthubAuthPluginInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {
    if (!array_key_exists('key', $configuration)) {
      $configuration['key'] = $this->configFactory->get('quanthub_auth.plugins.key')->get('key');
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getToken(): ?string {
    if (!$this->configuration['key']) {
      return NULL;
    }

    /** @var \Drupal\key\KeyInterface $key */
    $key = $this->entityTypeManager->getStorage('key')->load($this->configuration['key']);
    return $key?->getKeyValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): MarkupInterface|string {
    if (!$this->getToken()) {
      return $this->t('<strong>Inactive</strong>: key value is empty.');
    }
    return $this->t('<strong>Active</strong>');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('quanthub_auth.plugins.key');

    $form['key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Key'),
      '#default_value' => $config->get('key'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('quanthub_auth.plugins.key');
    $config->set('key', $form_state->getValue('key'))->save();
  }

}
