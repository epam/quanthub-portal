<?php

namespace Drupal\quanthub_watchlist\Controller;

use Drupal\Core\Block\BlockManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Quanthub Watchlist route.
 */
class WatchlistController extends ControllerBase {

  /**
   * The block manager service definition.
   *
   * @var \Drupal\Core\Block\BlockManager
   */
  protected BlockManager $pluginManagerBlock;

  /**
   * The current user service definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * {@inheritDoc}
   */
  public function __construct(BlockManager $plugin_manager_block, AccountProxy $current_user) {
    $this->pluginManagerBlock = $plugin_manager_block;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('current_user')
    );
  }

  /**
   * Build Artifact Browser block.
   */
  public function build(Request $request) {
    $block_manager = $this->pluginManagerBlock;

    // You can hard code configuration or you load from settings.
    $config = [];
    $plugin_block = $block_manager->createInstance('quanthub_watchlist_block', $config);

    // Some blocks might implement access check.
    $access_result = $plugin_block->access($this->currentUser);

    // Return empty render array if user doesn't have access.
    // $access_result can be boolean or an AccessResult class.
    if (is_object($access_result) && $access_result->isForbidden() || is_bool($access_result) && !$access_result) {
      // You might need to add some cache tags/contexts.
      return [];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['watchlist'],
      ],
      'element-content' => $plugin_block->build(),
      '#weight' => 0,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    $build['element-content']['#attached']['drupalSettings']['mode'] = 'watchlist';
    $build['element-content']['#attached']['drupalSettings']['query'] = $request->query->all();

    return $build;
  }

  /**
   * Returns translatable page title.
   */
  public function getTitle(): string {
    return $this->t('Watchlist');
  }

}
