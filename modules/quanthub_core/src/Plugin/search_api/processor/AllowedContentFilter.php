<?php

namespace Drupal\quanthub_core\Plugin\search_api\processor;

use Drupal\quanthub_core\AllowedContentManager;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\QueryInterface;
use Psr\Container\ContainerInterface;

/**
 * Excludes unpublished nodes from node indexes.
 *
 * @SearchApiProcessor(
 *   id = "allowed_content_filter",
 *   label = @Translation("Allowed Content Filter"),
 *   description = @Translation("Filtering content according to response data from WSO2."),
 *   stages = {
 *     "preprocess_query" = 0,
 *   },
 * )
 */
class AllowedContentFilter extends ProcessorPluginBase {

  /**
   * Dataset content type field with urn.
   */
  const QUANTHUB_URN_FIELD = 'field_quanthub_urn';

  /**
   * Used for content related on datasets like publications.
   */
  const QUANTHUB_URN_FIELD_RELATION = 'field_quanthub_urn_relation';


  /**
   * The allowed content manager service.
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
   * Static method create for factory.
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
  public function preprocessSearchQuery(QueryInterface $query) {
    if (getenv('WSO_IGNORE') !== 'TRUE') {
      $allowed_datasets = $this->allowedContentManager->getAllowedDatasetList();
    }
    if (!empty($allowed_datasets)) {
      $condition_group_or = $query->createConditionGroup('OR');
      $condition_group_or_relation = $query->createConditionGroup('OR');

      foreach ($allowed_datasets as $dataset) {
        $condition_group_or->addCondition(self::QUANTHUB_URN_FIELD, $dataset, 'EXACT');
        $condition_group_or_relation->addCondition(self::QUANTHUB_URN_FIELD_RELATION, $dataset, 'EXACT');
      }
      $condition_group_or->addCondition(self::QUANTHUB_URN_FIELD, NULL);
      $condition_group_or_relation->addCondition(self::QUANTHUB_URN_FIELD_RELATION, NULL);

      $query
        ->addConditionGroup($condition_group_or)
        ->addConditionGroup($condition_group_or_relation);
    }
  }

}
