<?php

namespace Drupal\quanthub_core\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * React on kernel events.
 */
class KernelEventsSubscriber implements EventSubscriberInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The media storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * Constructs a new KernelEventsSubscriber.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RouteMatchInterface $current_route_match, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentRouteMatch = $current_route_match;
    $this->mediaStorage = $entity_type_manager->getStorage('media');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::CONTROLLER_ARGUMENTS][] = ['onControllerArguments'];

    return $events;
  }

  /**
   * Modify Power BI display mode in WYSIWYG.
   */
  public function onControllerArguments(ControllerArgumentsEvent $event) {
    if ($this->currentRouteMatch->getRouteName() === 'media.filter.preview') {
      $request = $event->getRequest();
      $text = $request->query->get('text');
      $uuid = $request->query->get('uuid');
      if (!$text || !$uuid) {
        return;
      }
      /** @var \Drupal\media\MediaInterface $media */
      $media = current($this->mediaStorage->loadByProperties(['uuid' => $uuid]));
      if (!$media || $media->getSource()->getPluginId() !== 'power_bi') {
        return;
      }
      // Set "media_library" display for the preview rendering.
      $text = preg_replace(
        ['/data-view-mode(="[^"]*")?/', '/(data-entity-uuid="' . $uuid . '")/'],
        ['', '$1 data-view-mode="media_library"'],
        $text
      );
      $request->query->set('text', $text);
    }
  }

}
