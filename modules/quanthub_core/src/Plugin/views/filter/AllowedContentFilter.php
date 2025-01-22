<?php

namespace Drupal\quanthub_core\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\quanthub_core\AllowedContentManager;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Psr\Container\ContainerInterface;

/**
 * Filter by user allowed content in user data provided by xacml policies.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("allowed_content_filter")
 */
class AllowedContentFilter extends FilterPluginBase {

  /**
   * The Allowed Content Manager service.
   *
   * @var \Drupal\quanthub_core\AllowedContentManager
   */
  protected $allowedContentManager;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AllowedContentManager $allowed_content_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->allowedContentManager = $allowed_content_manager;
  }

  /**
   * Static method for factory.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('allowed_content_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {}

  /**
   * {@inheritdoc}
   */
  protected function operatorForm(&$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $account = $this->view->getUser();
    if (
      getenv('WSO_IGNORE') === 'TRUE' ||
      $account->hasPermission('bypass dataset access')
    ) {
      return;
    }

    $this->ensureMyTable();

    $field = "$this->tableAlias.$this->realField";
    /** @var \Drupal\Core\Database\Query\ConditionInterface $conditions */
    $conditions = $this->query->getConnection()->condition('OR');
    $conditions->isNull($field);

    if ($datasets = $this->allowedContentManager->getAllowedDatasetList()) {
      $conditions->condition($field, $datasets, 'IN');
    }

    $this->query->addWhere($this->options['group'], $conditions);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $account = $this->view->getUser();
    $contexts = ['user.permissions', 'user.roles:anonymous'];
    // Cache per user if we filter by individual user's datasets.
    if (!$account->hasPermission('bypass dataset access') && $account->isAuthenticated()) {
      $contexts[] = 'user.datasets';
    }
    return Cache::mergeContexts(
      parent::getCacheContexts(),
      $contexts
    );
  }

}
