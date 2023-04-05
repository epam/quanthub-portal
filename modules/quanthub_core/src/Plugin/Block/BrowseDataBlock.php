<?php

namespace Drupal\quanthub_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a browse data block.
 *
 * @Block(
 *   id = "quanthub_core_browse_data",
 *   admin_label = @Translation("Browse Data"),
 *   category = @Translation("Custom")
 * )
 */
class BrowseDataBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a LanguageBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $links = [
      'dataset_explorer' => Url::fromRoute('quanthub_datasetexplorer.explorer'),
      'code_lists' => Url::fromRoute('quanthub_codelists.codelists'),
      'api' => Url::fromUri('https://sdmx.org/?page_id=5008'),
    ];

    if (!empty($links)) {
      $build = [
        '#theme' => 'block__browse_data_menu',
        '#links' => $links,
      ];
    }
    return $build;
  }

}
