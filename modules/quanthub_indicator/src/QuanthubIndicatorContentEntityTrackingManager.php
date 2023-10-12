<?php

namespace Drupal\quanthub_indicator;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\quanthub_sdmx_sync\QuanthubSdmxClient;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Drupal\search_api\Task\TaskManagerInterface;

/**
 * Quanhub indicator tracking manager, added logic for tracking indicator CT.
 */
class QuanthubIndicatorContentEntityTrackingManager extends ContentEntityTrackingManager {

  /**
   * The SDMX client.
   *
   * @var \Drupal\quanthub_sdmx_sync\QuanthubSdmxClient
   */
  protected $sdmxClient;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\search_api\Task\TaskManagerInterface $taskManager
   *   The task manager.
   * @param \Drupal\quanthub_sdmx_sync\QuanthubSdmxClient $sdmxClient
   *   The SDMX client.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager, TaskManagerInterface $taskManager, QuanthubSdmxClient $sdmxClient) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->taskManager = $taskManager;
    $this->sdmxClient = $sdmxClient;
  }

  /**
   * Queues an entity for indexing.
   *
   * If "Index items immediately" is enabled for the index, the entity will be
   * indexed right at the end of the page request.
   *
   * When calling this method with an existing entity
   * (@code $new = FALSE @endcode), changes in the existing translations will
   * only be recognized if an appropriate @code $entity->original @endcode value
   * is set.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to be indexed.
   * @param bool $new
   *   (optional) TRUE if this is a new entity, FALSE if it already existed (and
   *   should already be known to the tracker).
   */
  public function trackEntityChange(ContentEntityInterface $entity, bool $new = FALSE) {
    // Check if the entity is a content entity.
    if (!empty($entity->search_api_skip_tracking)) {
      return;
    }

    $indexes = $this->getIndexesForEntity($entity);
    if (!$indexes) {
      return;
    }

    // Compare old and new languages for the entity to identify inserted,
    // updated and deleted translations (and, therefore, search items).
    $entity_id = $entity->id();
    $new_translations = array_keys($entity->getTranslationLanguages());
    $old_translations = [];
    if (!$new) {
      // In case we don't have the original, fall back to the current entity,
      // and assume no new translations were added.
      $original = $entity->original ?? $entity;
      $old_translations = array_keys($original->getTranslationLanguages());
    }
    $deleted_translations = array_diff($old_translations, $new_translations);
    $inserted_translations = array_diff($new_translations, $old_translations);
    $updated_translations = array_diff($new_translations, $inserted_translations);

    $datasource_id = 'entity:' . $entity->getEntityTypeid();
    $get_ids = function (string $langcode) use ($entity_id): string {
      return $entity_id . ':' . $langcode;
    };

    $inserted_ids = array_map($get_ids, $inserted_translations);
    $updated_ids = array_map($get_ids, $updated_translations);
    $deleted_ids = array_map($get_ids, $deleted_translations);

    $indicators = [];
    if ($entity->getType() == 'indicator') {
      $indicators = $this->getIndicatorsToIndex($entity);
    }

    foreach ($indexes as $index) {
      if ($inserted_ids) {
        $filtered_item_ids = static::filterValidItemids($index, $datasource_id, $inserted_ids);
        if ($filtered_item_ids) {
          if (!empty($indicators)) {
            foreach ($indicators as $indicator) {
              $filtered_indicator_id = $filtered_item_ids[0] . '_indicator_' . $indicator['id'];
              $index->trackItemsInserted($datasource_id, [$filtered_indicator_id]);
            }
          }
          else {
            $index->trackItemsInserted($datasource_id, $filtered_item_ids);
          }
        }
      }
      if ($updated_ids) {
        $filtered_item_ids = static::filterValidItemids($index, $datasource_id, $updated_ids);
        if ($filtered_item_ids) {
          if (!empty($indicators)) {
            foreach ($indicators as $indicator) {
              $filtered_indicator_id = $filtered_item_ids[0] . '_indicator_' . $indicator['id'];
              $index->trackItemsUpdated($datasource_id, [$filtered_indicator_id]);
            }
          }
          else {
            $index->trackItemsUpdated($datasource_id, $filtered_item_ids);
          }
        }
      }
      if ($deleted_ids) {
        $filtered_item_ids = static::filterValidItemids($index, $datasource_id, $deleted_ids);
        if ($filtered_item_ids) {
          if (!empty($indicators)) {
            foreach ($indicators as $indicator) {
              $filtered_indicator_id = $filtered_item_ids[0] . '_indicator_' . $indicator['id'];
              $index->trackItemsDeleted($datasource_id, [$filtered_indicator_id]);
            }
          }
          else {
            $index->trackItemsDeleted($datasource_id, $filtered_item_ids);
          }
        }
      }
    }
  }

  /**
   * Implements hook_entity_delete().
   *
   * Deletes all entries for this entity from the tracking table for each index
   * that tracks this entity type.
   *
   * By setting the $entity->search_api_skip_tracking property to a true-like
   * value before this hook is invoked, you can prevent this behavior and make
   * the Search API ignore this deletion. (Note that this might lead to stale
   * data in the tracking table or on the server, since the item will not
   * removed from there (if it has been added before).)
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The deleted entity.
   *
   * @see search_api_entity_delete()
   */
  public function entityDelete(EntityInterface $entity) {
    // Check if the entity is a content entity.
    if (!($entity instanceof ContentEntityInterface)
      || !empty($entity->search_api_skip_tracking)) {
      return;
    }

    $indexes = $this->getIndexesForEntity($entity);
    if (!$indexes) {
      return;
    }

    $indicators_ids = [];
    if ($entity->getType() == 'indicator') {
      $indicators_ids = $this->getIndicatorsToIndex($entity);
    }

    // Remove the search items for all the entity's translations.
    $item_ids = [];
    $entity_id = $entity->id();
    foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
      $item_ids[] = $entity_id . ':' . $langcode;
      if (!empty($indicators_ids)) {
        foreach ($indicators_ids as $indicators_id) {
          $item_ids[] = $entity_id . ':' . $langcode . '_indicator_' . $indicators_id;
        }
      }
    }
    $datasource_id = 'entity:' . $entity->getEntityTypeId();
    foreach ($indexes as $index) {
      $index->trackItemsDeleted($datasource_id, $item_ids);
    }
  }

  /**
   * Get Dataset indicators from SDMX.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The indicator entity.
   *
   * @return array|null
   *   Return null or array of dataset indicators.
   */
  private function getIndicatorsToIndex(ContentEntityInterface $entity) {
    $dataset_urn = $entity
      ->field_dataset
      ->first()
      ->entity
      ->field_quanthub_urn
      ->getString();

    $dimension_id = $entity->field_indicator_parameter->getString();

    return $this->sdmxClient->datasetIndicators($dataset_urn, $dimension_id);
  }

}
