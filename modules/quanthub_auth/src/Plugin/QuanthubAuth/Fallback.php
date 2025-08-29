<?php

namespace Drupal\quanthub_auth\Plugin\QuanthubAuth;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\quanthub_auth\QuanthubAuthInterface;
use Drupal\quanthub_auth\QuanthubAuthPluginInterface;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides anonymous token fallback for authenticated user.
 *
 * @QuanthubAuth(
 *   id = "fallback",
 *   scope = \Drupal\quanthub_auth\QuanthubAuthPluginInterface::AUTHENTICATED,
 *   label = @Translation("Anonymous token fallback for authenticated user"),
 *   priority = -100
 * )
 */
class Fallback extends PluginBase implements QuanthubAuthPluginInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('quanthub.auth'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected QuanthubAuthInterface $quanthubAuth,
  ) {
    if (!array_key_exists('roles', $configuration)) {
      $configuration['roles'] = $this->configFactory->get('quanthub_auth.plugins.fallback')->get('roles');
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getToken(): ?string {
    if (!$this->configuration['roles']) {
      return NULL;
    }

    if (array_intersect($this->configuration['roles'], $this->currentUser->getRoles())) {
      return $this->quanthubAuth->getAnonymousToken();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): MarkupInterface|string {
    if (!$this->configuration['roles']) {
      return $this->t('<strong>Inactive</strong>: disabled.');
    }
    return $this->t('<strong>Active</strong> for roles: @roles.', [
      '@roles' => implode(', ', $this->configuration['roles']),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('quanthub_auth.plugins.fallback');

    $roles = [];
    /** @var \Drupal\user\RoleInterface $role */
    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple() as $role) {
      if ($role->id() === RoleInterface::ANONYMOUS_ID) {
        continue;
      }
      $roles[$role->id()] = $role->label();
    }

    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#options' => $roles,
      '#default_value' => $config->get('roles') ?? [],
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
    $config = $this->configFactory->getEditable('quanthub_auth.plugins.fallback');
    $config->set('roles', array_filter($form_state->getValue('roles', [])))->save();
  }

}
