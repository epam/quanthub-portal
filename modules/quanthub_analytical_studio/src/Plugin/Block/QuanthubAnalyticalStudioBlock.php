<?php

namespace Drupal\quanthub_analytical_studio\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Quanthub Analytical Studio' block.
 *
 * @Block(
 *  id = "quanthub_analytical_studio_block",
 *  admin_label = @Translation("Quanthub Analytical Studio"),
 * )
 */
class QuanthubAnalyticalStudioBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['quanthub_analytical_studio_block'] = [
      '#markup' => '<div id="analytical-studio">
      <div class="spinner spinner--explorer">
      <div class="spinner__bg">
      <div class="spinner__circle"></div>
      </div></div></div>',
      '#attached' => [
        'library' => 'quanthub_analytical_studio/analytical-studio',
        'drupalSettings' => [],
      ],
    ];

    return $build;
  }

}
