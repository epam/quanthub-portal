<?php

namespace Drupal\quanthub_datasetexplorer\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;

/**
 * Main controller for DatasetExplorer.
 */
final class DatasetExplorer extends ControllerBase {

  /**
   * Build explorer block.
   */
  public function __invoke() {
    $sdmx_config = $this->config('quanthub.settings')->get('third_party_settings.quanthub_sdmx');
    $config = $this->config('quanthub.settings')->get('third_party_settings.quanthub_datasetexplorer');

    $settings = [
      'workspaceId' => $sdmx_config['workspace'] ?? NULL,
      'factorAttributeId' => $config['factor_attribute_id'] ?? NULL,
      'uomAttributeId' => $config['uom_attribute_id'] ?? NULL,
      'hierarchy' => !empty($config['hierarchy']),
    ];

    $build = [
      '#theme' => 'dataset_explorer',
      '#attached' => [
        'drupalSettings' => [
          'datasetExplorer' => $settings,
          // @todo remove this compatability layer on library change.
          'workspaceId' => $settings['workspaceId'] ?? '',
          'dataAttributeId' => $settings['factorAttributeId'] ?? '',
          'unitsOfMeasureId' => $settings['uomAttributeId'] ?? '',
          'facetsLoadHierarchies' => $settings['hierarchy'] ? 'TRUE' : 'FALSE',
        ],
      ],
    ];

    (new CacheableMetadata())
      ->addCacheableDependency($this->config('quanthub.settings'))
      ->applyTo($build);

    return $build;
  }

}
