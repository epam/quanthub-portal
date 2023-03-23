<?php

namespace Drupal\quanthub_codelists\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Quanthub Code Lists' block.
 *
 * @Block(
 *  id = "quanthub_codelists_block",
 *  admin_label = @Translation("Quanthub Code Lists"),
 * )
 */
class QuanthubCodeListsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['quanthub_codelists_block'] = [
      '#markup' => '<div id="codelists"></div>',
      '#attached' => [
        'library' => 'quanthub_codelists/codelists',
        'drupalSettings' => [],
      ],
    ];

    return $build;
  }

}
