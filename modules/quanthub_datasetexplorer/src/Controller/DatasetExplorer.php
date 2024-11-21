<?php

namespace Drupal\quanthub_datasetexplorer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Main controller for DatasetExplorer.
 */
final class DatasetExplorer extends ControllerBase {

  /**
   * Build explorer block.
   */
  public function explorer(Request $request) {
    $build = [
      '#theme' => 'datasetexplorer_block',
      '#attached' => [
        'library' => 'quanthub_datasetexplorer/dataset-explorer',
      ],
    ];

    $build['element-content']['#attached']['drupalSettings']['mode'] = 'explorer';
    $build['element-content']['#attached']['drupalSettings']['query'] = $request->query->all();

    return $build;
  }

  /**
   * Returns translatable page title.
   */
  public function getTitle(): string {
    return $this->t('Dataset Explorer');
  }

}
