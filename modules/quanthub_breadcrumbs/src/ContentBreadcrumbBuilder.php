<?php

namespace Drupal\quanthub_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\node\NodeInterface;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Link;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\Views;

/**
 * Provides a breadcrumb builder for Content types.
 * Based on the mapping content type => general content type view page.
 */
class ContentBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private mixed $entityTypeManager;

  /**
   * Constructs a QuanthubBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager->getStorage('node');
  }

  public function getContentTypeMap (): array {
    return [
      'dataset' => 'search_datasets.navigator',
      'external_dataset' => 'search_datasets.navigator',
      'press_release' => 'search_publications.navigator',
      'news' => 'search_news.navigator',
      'release' => 'releases.page_1',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    $node = $route_match->getParameter('node');
    $contentTypeMap = $this->getContentTypeMap();

    return $node instanceof NodeInterface && array_key_exists($node->getType(), $contentTypeMap);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = new Breadcrumb();

    /** @var \Drupal\node\Entity\Node $node */
    $node = $route_match->getParameter('node');
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    $contentTypeMap = $this->getContentTypeMap();
    if (isset($contentTypeMap[$node->getType()])) {
      $viewMapId = $contentTypeMap[$node->getType()];
      [$viewId, $displayId] = explode('.', $viewMapId);

      $view = Views::getView($viewId);
      $view->setDisplay($displayId);

      // Get the title of the view
      $viewTitle = $view->getTitle();

      $breadcrumb->addLink(Link::createFromRoute($viewTitle, 'view.' . $contentTypeMap[$node->getType()]));
    }

    $breadcrumb->addLink(Link::createFromRoute($node->getTitle(), '<none>'));

    $breadcrumb->addCacheContexts(['route', 'languages']);
    $breadcrumb->addCacheTags(['node:' . $node->id()]);

    return $breadcrumb;
  }

}
