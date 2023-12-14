<?php

namespace Drupal\quanthub_breadcrumbs;

use Drupal\book\BookBreadcrumbBuilder;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;

/**
 * Provides a breadcrumb builder for Books.
 *
 * Extend the core BookBreadcrumbBuilder and add the current node title item.
 */
class BooksBreadcrumbBuilder extends BookBreadcrumbBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = parent::build($route_match);

    if ($breadcrumb) {
      $node = $route_match->getParameter('node');

      if ($node instanceof NodeInterface) {
        $breadcrumb->addLink(Link::createFromRoute($node->getTitle(), '<none>'));
      }
    }

    return $breadcrumb;
  }

}
