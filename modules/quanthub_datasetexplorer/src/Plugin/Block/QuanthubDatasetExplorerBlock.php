<?php

namespace Drupal\quanthub_datasetexplorer\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Quanthub Dataset Explorer' block.
 *
 * @Block(
 *  id = "quanthub_datasetexplorer_block",
 *  admin_label = @Translation("Quanthub Dataset Explorer"),
 * )
 */
class QuanthubDatasetExplorerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['quanthub_datasetexplorer_block'] = [
      '#markup' => '<div id="dataset_explorer"> 
      <div class="spinner spinner--explorer">
      <div class="spinner__bg">
      <div class="spinner__circle"></div>
      </div></div></div>',
      '#attached' => [
        'library' => 'quanthub_datasetexplorer/dataset-explorer',
        'drupalSettings' => [],
      ],
    ];

    return $build;
  }

}
