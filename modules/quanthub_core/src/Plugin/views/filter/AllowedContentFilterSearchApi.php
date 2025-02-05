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

    if ($this->allowedContentManager->getAllowedDatasetList()) {
      $datasets = $this->allowedContentManager->getProhibitedDatasetList();
      $this->query->addWhere($this->options['group'], $this->realField, $datasets, 'NOT IN');
    }
    else {
      $this->query->addWhere($this->options['group'], $this->realField, NULL, 'IS NULL');
    }
  }

}
