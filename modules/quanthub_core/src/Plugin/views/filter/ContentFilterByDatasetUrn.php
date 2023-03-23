<?php

namespace Drupal\quanthub_core\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Psr\Container\ContainerInterface;
use Drupal\quanthub_core\AllowedContentManager;

/**
 * Filter by user allowed content in user data provided by xacml policies.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("content_filter_by_dataset_urn")
 */
class ContentFilterByDatasetUrn extends FilterPluginBase {

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
  public function query() {
    $datasets = [];

    $this->ensureMyTable();
    $datasets = $this->allowedContentManager->getAllowedDatasetList();

    $field = "$this->tableAlias.{$this->realField}_value";

    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;

    // Added filter in view SQL for filtering according to user allowed content
    // in user data provided by xacml.
    if (!empty($datasets)) {
      if (count($datasets) > 1) {
        $or_conditions = $this->query->getConnection()->condition('OR');
        foreach ($datasets as $dataset) {
          if (str_ends_with($dataset, ')')) {
            $or_conditions->condition($field, $dataset);
          }
          else {
            $or_conditions->condition($field, $dataset . '%', 'LIKE');
          }
        }

        $query->addWhere($this->options['group'], $or_conditions);
      }
      elseif (count($datasets) == 1) {
        $dataset = reset($datasets);
        if (str_ends_with($dataset, ')')) {
          $query->addWhere($this->options['group'], $field);
        }
        else {
          $query->addWhere(
            $this->options['group'],
            $field,
            $dataset . '%',
            'LIKE'
          );
        }
      }
    }
  }

}
