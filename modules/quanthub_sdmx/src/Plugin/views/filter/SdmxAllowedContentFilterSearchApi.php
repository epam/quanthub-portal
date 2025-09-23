<?php

namespace Drupal\quanthub_sdmx\Plugin\views\filter;

use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;

/**
 * Filter by user allowed content in user data provided by xacml policies.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("sdmx_allowed_content_filter_search_api")
 */
class SdmxAllowedContentFilterSearchApi extends SdmxAllowedContentFilter {

  use SearchApiFilterTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $datasets = $this->urnStorage->getAllowedDatasets();
    if ($datasets === NULL) {
      return;
    }

    $this->ensureMyTable();

    if ($datasets) {
      $prohibited = $this->urnStorage->getProhibitedDatasets();
      $this->query->addWhere($this->options['group'], $this->realField, $prohibited, 'NOT IN');
    }
    else {
      $this->query->addWhere($this->options['group'], $this->realField, NULL, 'IS NULL');
    }
  }

}
