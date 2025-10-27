<?php

namespace Drupal\quanthub_indicator;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

// @note: You only need Reference, if you want to change service arguments.
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the language manager service.
 */
class QuanthubIndicatorServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('search_api.entity_datasource.tracking_manager')) {
      $definition = $container->getDefinition('search_api.entity_datasource.tracking_manager');
      $definition->setClass('Drupal\quanthub_indicator\QuanthubIndicatorContentEntityTrackingManager');
      if (!$definition->getArguments()) {
        $definition->addArgument(new Reference('entity_type.manager'));
        $definition->addArgument(new Reference('language_manager'));
        $definition->addArgument(new Reference('search_api.task_manager'));
      }
      $definition->addArgument(new Reference('sdmx_client'));
      $definition->addArgument(new Reference('database'));
    }
  }

}
