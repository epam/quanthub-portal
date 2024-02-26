<?php

namespace Drupal\quanthub_statgpt\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Quanthub StatGPT' block.
 *
 * @Block(
 *   id = "quanthub_statgpt_block",
 *   admin_label = @Translation("Quanthub StatGPT"),
 * )
 */
class StatGPTBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['quanthub_statgpt_block'] = [
      '#markup' => '<div id="statgpt">
      <div class="spinner spinner--statgpt">
      <div class="spinner__bg">
      <div class="spinner__circle"></div>
      </div></div></div>',
      '#attached' => [
        'library' => 'quanthub_statgpt/statgpt',
        'drupalSettings' => [],
      ],
    ];

    return $build;
  }

}
