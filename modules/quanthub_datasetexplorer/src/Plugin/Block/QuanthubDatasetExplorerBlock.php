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
    $build['datasetexplorer_block'] = [
      '#theme' => 'datasetexplorer_block',
      '#attached' => [
        'library' => 'quanthub_datasetexplorer/dataset-explorer',
      ],
    ];

    return $build;
  }

}
