<?php

namespace Drupal\quanthub_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Provides a breadcrumb builder for Books.
 */
class BooksBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a BooksBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManager $entity_type_manager,
    LanguageManagerInterface $language_manager
  ) {
    $this->entityTypeManager = $entity_type_manager->getStorage('node');
    $this->languageManager = $language_manager;
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
    $currentLanguage = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    /** @var \Drupal\node\Entity\Node $node */
    $node = $route_match->getParameter('node');
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    if ($node->bundle() == 'book') {
      // Book Name breadcrumb item for all children elements.
      $bookId = $node->book['bid'];
      $bookNode = $this->entityTypeManager->load($bookId);

      if ($bookNode instanceof NodeInterface && $bookNode->hasTranslation($currentLanguage)) {
        $breadcrumb->addLink($bookNode->getTranslation($currentLanguage)->toLink());
      }
      $breadcrumb->addCacheableDependency($bookNode);
    }

    if ($node->bundle() == 'book_content') {
      // Get first referenced node.
      $referencedPageId = $node->field_book_page_ref->first()->getString();
      $referencedNode = $this->entityTypeManager->load($referencedPageId);

      // Get book node by id from referenced node.
      $bookId = $referencedNode->book['bid'];
      $bookNode = $this->entityTypeManager->load($bookId);
      // Book Name item.
      if ($bookNode instanceof NodeInterface && $bookNode->hasTranslation($currentLanguage)) {
        $breadcrumb->addLink($bookNode->getTranslation($currentLanguage)->toLink());
      }
      // Book content node Title item.
      $breadcrumb->addLink(Link::createFromRoute($node->getTitle(), '<none>'));
      $breadcrumb->addCacheableDependency($bookNode);
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
