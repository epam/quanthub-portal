<?php

namespace Drupal\quanthub_core\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The Allowed Content Manager service.
   *
   * @var \Drupal\quanthub_core\AllowedContentManager
   */
  protected $allowedContentManager;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    AllowedContentManager $allowed_content_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
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
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('allowed_content_manager'),
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
      // Set TRUE condition to properly use OR groups.
      $this->query->addWhereExpression($this->options['group'], 'TRUE');
      return;
    }

    $datasets = $this->allowedContentManager->getAllowedDatasetList() ?: NULL;
    /** @var \Drupal\Core\Database\Query\ConditionInterface $conditions */
    $conditions = $this->query->getConnection()->condition('AND');
    $base_table = $this->relationship ?: $this->view->storage->get('base_table');

    foreach ($this->getNodeTypesMapping() as $field => $info) {
      $alias = "subquery_$field";
      $operator = $datasets ? 'NOT IN' : 'IS NOT NULL';

      /** @var \Drupal\Core\Database\Query\ConditionInterface $condition */
      $condition = $this->query->getConnection()->condition('OR');
      $condition->condition("$base_table.type", $info['bundles'], 'NOT IN');
      $conditions->condition($condition);

      if ($field === '_root') {
        /** @var \Drupal\Core\Database\Query\SelectInterface $subquery */
        $subquery = $this->query->getConnection()
          ->select($this->table, $alias)
          ->fields($alias, [$this->realField])
          ->condition("$alias.$this->realField", $datasets, $operator)
          ->where("$alias.entity_id = $base_table.nid AND $alias.deleted = 0");
        $condition->notExists($subquery);
        continue;
      }

      $data_alias = $alias . '_data';
      $ref_alias = $alias . '_reference';
      /** @var \Drupal\Core\Database\Query\SelectInterface $subquery */
      $subquery = $this->query->getConnection()
        ->select($info['table'], $ref_alias)
        ->fields($alias, [$this->realField])
        ->condition("$alias.$this->realField", $datasets, $operator)
        ->where("$base_table.nid = $ref_alias.entity_id");
      $subquery->innerJoin($info['data_table'], $data_alias, "$data_alias.nid = $ref_alias.{$info['column']} AND $data_alias.status = 1");
      $subquery->leftJoin($this->table, $alias, "$alias.entity_id = $data_alias.nid AND $alias.deleted = 0");
      $condition->notExists($subquery);
    }
    $this->query->addWhere($this->options['group'], $conditions);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = ['user.permissions', 'user.datasets'];
    return Cache::mergeContexts(
      parent::getCacheContexts(),
      $contexts
    );
  }

  /**
   * Helper function to collect node bundles fields.
   */
  protected function getNodeTypesMapping() {
    $mapping = [];

    /** @var \Drupal\Core\Entity\Sql\TableMappingInterface $table_mapping */
    $table_mapping = $this->entityTypeManager->getStorage('node')->getTableMapping();
    $bundles = $this->entityFieldManager->getFieldMap()['node']['nid']['bundles'];
    $field_name = $this->definition['field_name'] ?? $this->allowedContentManager::URN_FIELD;

    foreach ($bundles as $bundle) {
      $definitions = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
      if (empty($definitions[$field_name])) {
        continue;
      }
      if (!$definitions[$field_name]->isComputed()) {
        $mapping['_root']['bundles'][$bundle] = $bundle;
        continue;
      }
      $base_field = $definitions[$field_name]->getSetting('field_reference_name');
      if ($base_field && $definitions[$base_field]->getFieldStorageDefinition()->getMainPropertyName() === 'target_id') {
        $mapping[$base_field]['table'] = $table_mapping->getFieldTableName($base_field);
        $mapping[$base_field]['data_table'] = $table_mapping->getDataTable() ?? $table_mapping->getBaseTable();
        $mapping[$base_field]['column'] = "{$base_field}_target_id";
        $mapping[$base_field]['bundles'][$bundle] = $bundle;
      }
    }

    // Sort just for more readable SQL, root case first.
    ksort($mapping);

    return $mapping;
  }

}
