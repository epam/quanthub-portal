<?php

namespace Drupal\quanthub_core\Plugin\EventSubscriber;

use Drupal\elasticsearch_connector\Event\PrepareSearchQueryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Quanthub Event subscriber for improvements search query.
 *
 * @package Drupal\quanthub_core\EventSubscriber
 */
class QuanthubElasticSearchEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [PrepareSearchQueryEvent::PREPARE_QUERY => 'prepareQuery'];
  }

  /**
   * Changed filter query for using elastic multi match and phrase_prefix.
   *
   * @param \Drupal\elasticsearch_connector\Event\PrepareSearchQueryEvent $event
   *   The event entity.
   */
  public function prepareQuery(PrepareSearchQueryEvent $event) {
    $query = $event->getElasticSearchQuery();
    $query['query_search_string']['multi_match'] = $query['query_search_string']['query_string'];
    $query['query_search_string']['multi_match']['type'] = 'phrase_prefix';
    unset($query['query_search_string']['query_string']);

    $event->setElasticSearchQuery($query);
  }

}
