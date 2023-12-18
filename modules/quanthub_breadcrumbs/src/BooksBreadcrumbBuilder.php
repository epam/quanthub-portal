<?php

namespace Drupal\quanthub_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Provides a breadcrumb builder for Books.
 *
 * Extend the core BookBreadcrumbBuilder and add the current node title item.
 */
class BooksBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * Constructs a QuanthubBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');
    if ($node instanceof NodeInterface) {
      $bundle = $node->bundle();
      return $bundle === 'book' || $bundle === 'book_content';
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = new Breadcrumb();

    /** @var \Drupal\node\Entity\Node $node */
    $node = $route_match->getParameter('node');
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    if ($node->bundle() == 'book') {
      // Book Name breadcrumb item for all children elements.
      $bookId = $node->book['bid'];
      $bookNode = $this->entityTypeManager->load($bookId);

      $breadcrumb->addLink($bookNode->toLink());
    }

    if ($node->bundle() == 'book_content') {
      // Get first referenced node.
      $referencedPageId = $node->field_book_page_ref->first()->getString();
      $referencedNode = $this->entityTypeManager->load($referencedPageId);

      // Get book node by id from referenced node.
      $bookId = $referencedNode->book['bid'];
      $bookNode = $this->entityTypeManager->load($bookId);
      // Book Name item.
      $breadcrumb->addLink($bookNode->toLink());
      // Book content node Title item.
      $breadcrumb->addLink(Link::createFromRoute($node->getTitle(), '<none>'));
    }

    $parameters = $route_match->getParameters();
    foreach ($parameters as $parameter) {
      if ($parameter instanceof CacheableDependencyInterface) {
        $breadcrumb->addCacheableDependency($parameter);
      }
    }

    $breadcrumb->addCacheContexts(['route', 'url.path', 'languages']);

    return $breadcrumb;
  }

}
