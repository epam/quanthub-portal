<?php

namespace Drupal\quanthub_datasetexplorer\Controller;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Main controller for DatasetExplorer.
 */
final class DatasetExplorer extends ControllerBase {

  /**
   * The block manager service definition.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * {@inheritDoc}
   */
  public function __construct(BlockManagerInterface $block_manager) {
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block')
    );
  }

  /**
   * Build slices block.
   */
  public function slices(Request $request) {
    $build = $this->explorer($request);
    $build['element-content']['#attached']['drupalSettings']['mode'] = 'slices';
    return $build;
  }

  /**
   * Build explorer block.
   */
  public function explorer(Request $request) {
    $block = $this->blockManager
      ->createInstance('quanthub_datasetexplorer_block')
      ->build();
    $block['#attached']['drupalSettings']['mode'] = 'explorer';
    $block['#attached']['drupalSettings']['query'] = $request->query->all();
    return [
      '#type' => 'container',
      'element-content' => $block,
      '#attributes' => [
        'class' => ['datasetexplorer'],
      ],
      '#cache' => [
        'contexts' => ['url.query_args'],
      ],
    ];
  }

  /**
   * Returns translatable page title.
   */
  public function getTitle(): string {
    return $this->t('Data Bank');
  }

  /**
   * Check access.
   */
  public function access(AccountProxyInterface $account): AccessResultInterface {
    /** @var \Drupal\Core\Block\BlockPluginInterface $block */
    $block = $this->blockManager
      ->createInstance('quanthub_datasetexplorer_block');
    return $block->access($account, TRUE);
  }

}
