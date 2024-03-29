<?php

namespace Drupal\quanthub_book\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Quanthub Book Title' block.
 *
 * @Block(
 *  id = "quanthub_book_title",
 *  admin_label = @Translation("Quanthub Book Title"),
 * )
 */
class QuanthubBookTitle extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The node storage controller.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private RouteMatchInterface $currentRouteMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, NodeStorageInterface $node_storage, RouteMatchInterface $currentRouteMatch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentRouteMatch = $currentRouteMatch;
    $this->nodeStorage = $node_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->currentRouteMatch->getParameter('node');
    $build = [];

    if ($node instanceof NodeInterface && $node->getType() == 'book' && !empty($node->book['bid'])) {
      $this->nodeStorage->load($node->book['bid']);
      $build['quanthub_book_title'] = [
        '#markup' => '<h1 class="quanthub_book_title display-3 xs:header-2 page-title mb-6">' . $book_main_node->getTitle() . '</h1>',
      ];
    }

    return $build;
  }

}
