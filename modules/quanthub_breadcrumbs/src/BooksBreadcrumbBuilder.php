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
  public function applies(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');
    if ($node instanceof NodeInterface) {
      $bundle = $node->bundle();
      return $bundle === 'book' || $bundle === 'book_content';
    }
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = parent::build($route_match);
    $node = $route_match->getParameter('node');
    if ($breadcrumb) {
      if ($node->bundle() == 'book') {
        $breadcrumb->addLink(Link::createFromRoute($node->getTitle(), '<none>'));
      }

      if ($node->bundle() == 'book_content') {
        $parentPageId = $node->get('field_book_page_ref')->first()->getString();
        $book = $this->nodeStorage->load($parentPageId)->book;

        $depth = 1;
        $bookNodeIds = [];
        while (!empty($book['p' . ($depth + 1)])) {
          $bookNodeIds[] = $book['p' . $depth];
          $depth++;
        }
        /** @var \Drupal\node\NodeInterface[] $parentBooks */
        $parentBooks = $this->nodeStorage->loadMultiple($bookNodeIds);
        $parentBooks = array_map([$this->entityRepository, 'getTranslationFromContext'], $parentBooks);
        if (count($parentBooks) > 0) {
          $depth = 1;
          while (!empty($book['p' . ($depth + 1)])) {
            if (!empty($parentBooks[$book['p' . $depth]]) && ($parentBook = $parentBooks[$book['p' . $depth]])) {
              $breadcrumb->addCacheableDependency($parentBook);
              $breadcrumb->addLink($parentBook->toLink());
            }
            $depth++;
          }
        }
        $breadcrumb->addLink(Link::createFromRoute($node->getTitle(), '<none>'));
      }
    }

    return $breadcrumb;
  }

}
