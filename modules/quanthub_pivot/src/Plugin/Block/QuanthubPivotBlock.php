<?php

namespace Drupal\quanthub_pivot\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Quanthub Pivot' block.
 *
 * @Block(
 *  id = "quanthub_pivot_block",
 *  admin_label = @Translation("Quanthub Pivot"),
 * )
 */
class QuanthubPivotBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['pivot_block'] = [
      '#markup' => '<div id="pivot"></div>',
      '#attached' => [
        'library' => 'quanthub_pivot/pivot',
        'drupalSettings' => [],
      ],
    ];

    return $build;
  }

}
