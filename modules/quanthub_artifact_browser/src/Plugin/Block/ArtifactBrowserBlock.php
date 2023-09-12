<?php

namespace Drupal\quanthub_artifact_browser\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Quanthub Artifact Browser' block.
 *
 * @Block(
 *   id = "quanthub_artifact_browser_block",
 *   admin_label = @Translation("Quanthub Artifact Browser"),
 * )
 */
class ArtifactBrowserBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['quanthub_artifact_browser_block'] = [
      '#markup' => '<div id="artifact_browser"></div>',
      '#attached' => [
        'library' => 'quanthub_artifact_browser/artifact_browser',
        'drupalSettings' => [],
      ],
    ];

    return $build;
  }

}
