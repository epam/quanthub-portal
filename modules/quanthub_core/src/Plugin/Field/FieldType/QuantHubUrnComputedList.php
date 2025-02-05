<?php

namespace Drupal\quanthub_core\Plugin\Field\FieldType;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\quanthub_core\AllowedContentManager;

/**
 * Computed field to proxy QuantHub URN from references.
 */
class QuantHubUrnComputedList extends FieldItemList implements CacheableDependencyInterface {

  use ComputedItemListTrait;
  use RefinableCacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $this->list = [];

    $entity = $this->getEntity();
    $field_name = $this->getFieldDefinition()->getSetting('field_reference_name');
    if (!$field_name || !$entity->hasField($field_name)) {
      return;
    }
    $references = $entity->get($field_name);
    if (!$references instanceof EntityReferenceFieldItemListInterface) {
      return;
    }

    $urns = [];
    foreach ($references->referencedEntities() as $referencedEntity) {
      if (
        !$referencedEntity instanceof FieldableEntityInterface ||
        !$referencedEntity->hasField(AllowedContentManager::URN_FIELD)
      ) {
        continue;
      }

      $this->addCacheableDependency($referencedEntity);

      $field = $referencedEntity->get(AllowedContentManager::URN_FIELD);
      if ($field instanceof CacheableDependencyInterface) {
        $this->addCacheableDependency($field);
      }

      foreach ($field as $item) {
        if ($item instanceof CacheableDependencyInterface) {
          $this->addCacheableDependency($item);
        }
        if ($item->value) {
          $urns[] = $item->value;
        }
      }
    }

    foreach (array_values(array_unique($urns)) as $delta => $urn) {
      $this->list[$delta] = $this->createItem($delta, $urn);
    }
  }

}
