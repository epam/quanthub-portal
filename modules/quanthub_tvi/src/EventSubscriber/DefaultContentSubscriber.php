<?php

namespace Drupal\quanthub_tvi\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Default Content events subscriber.
 */
final class DefaultContentSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a DefaultContentSubscriber object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * On import event.
   *
   * @param \Drupal\default_content\Event\ImportEvent $event
   *   The import event.
   */
  public function onImport(Event $event): void {
    if (
      !$this->configFactory->get('quanthub.settings')->get('content.import') ||
      $event->getModule() !== 'quanthub_tvi'
    ) {
      return;
    }

    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'listings']);
    foreach ($event->getImportedEntities() as $entity) {
      if (!$entity instanceof MediaInterface) {
        continue;
      }
      if (($term = array_shift($terms))) {
        $term->set('field_background', $entity->id())->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Use string to not create hard dependency.
    return [
      'default_content.import' => ['onImport'],
    ];
  }

}
