<?php

namespace Drupal\quanthub_indicator\Plugin\search_api\tracker;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\search_api\Plugin\search_api\tracker\Basic;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a tracker implementation which uses a FIFO-like processing order.
 *
 *  @SearchApiTracker(
 *   id = "quanthub_default",
 *   label = @Translation("Quanthub Default"),
 *   description = @Translation("Quanthub tracker for supporting indicators")
 * )
 */
class QuanthubTracker extends Basic {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $tracker */
    $tracker = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $tracker->setEntityTypeManager($container->get('entity_type.manager'));

    return $tracker;
  }

  /**
   * Method DI.
   */
  public function setEntityTypeManager(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

}
