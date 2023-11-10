<?php

namespace Drupal\quanthub_indicator\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class EntityTypeSubscriber.
 */
class QuanthubIndicatorEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiEvents::GATHERING_DATA_SOURCES => 'gatheringDataSources',
    ];
  }

  /**
   * React to gathering data sources by search api.
   *
   * @param \Drupal\Component\EventDispatcher\Event $event
   *   Config crud event.
   */
  public function gatheringDataSources(Event $event) {
    $definitions = &$event->getDefinitions();
    if (!empty($definitions['entity:node']['class'])) {
      $definitions['entity:node']['class'] = 'Drupal\quanthub_indicator\Plugin\search_api\datasource\QuanthubContentEntity';
    }
  }

}
