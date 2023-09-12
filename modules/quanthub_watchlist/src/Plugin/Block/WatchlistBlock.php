<?php

namespace Drupal\quanthub_watchlist\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Quanthub Watchlist' block.
 *
 * @Block(
 *   id = "quanthub_watchlist_block",
 *   admin_label = @Translation("Quanthub Watchlist"),
 * )
 */
class WatchlistBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['quanthub_watchlist_block'] = [
      '#markup' => '<div id="watchlist"></div>',
      '#attached' => [
        'library' => 'quanthub_watchlist/watchlist',
        'drupalSettings' => [],
      ],
    ];

    return $build;
  }

}
