<?php

namespace Drupal\quanthub_auth\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines quanthub_auth annotation object.
 *
 * @Annotation
 */
final class QuanthubAuth extends Plugin {

  /**
   * The plugin ID.
   */
  public readonly string $id;

  /**
   * The plugin scope.
   */
  public readonly string $scope;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   */
  public readonly string $title;

  /**
   * The priority of the plugin.
   */
  public readonly int $priority;

}
