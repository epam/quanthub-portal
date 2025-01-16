<?php

namespace Drupal\quanthub_core\Plugin\views\filter;

use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;

/**
 * Filter by user allowed content in user data provided by xacml policies.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("allowed_content_filter_search_api")
 */
class AllowedContentFilterSearchApi extends AllowedContentFilter {

  use SearchApiFilterTrait;

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

    $conditions = $this->getQuery()->createConditionGroup('OR');
    $conditions->addCondition($this->realField, NULL, '=');

    if ($datasets = $this->allowedContentManager->getAllowedDatasetList()) {
      $conditions->addCondition($this->realField, $datasets, 'IN');
    }

    $this->query->addConditionGroup($conditions, $this->options['group']);
  }

}
