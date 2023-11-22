<?php

namespace Drupal\quanthub_indicator;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\quanthub_sdmx_sync\QuanthubSdmxClient;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Drupal\search_api\Task\TaskManagerInterface;

/**
 * Quanthub indicator tracking manager, added logic for tracking indicator CT.
 */
class QuanthubIndicatorContentEntityTrackingManager extends ContentEntityTrackingManager {

  /**
   * The SDMX client.
   *
   * @var \Drupal\quanthub_sdmx_sync\QuanthubSdmxClient
   */
  protected $sdmxClient;

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
    TaskManagerInterface $taskManager,
    QuanthubSdmxClient $sdmxClient,
    Connection $database
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->taskManager = $taskManager;
    $this->sdmxClient = $sdmxClient;
    $this->database = $database;
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
          if ($entity->getType() == 'indicator') {
            if (!empty($indicators)) {
              foreach ($inserted_translations as $inserted_translation) {
                if ($entity->hasTranslation($inserted_translation)) {
                  $filtered_indicator_id = [];
                  foreach ($indicators as $indicator) {
                    $filtered_indicator_id[] = $this->prepareSearchApiEntityKey($entity->getTranslation($inserted_translation)) . '_indicator_' . $indicator['id'];
                  }
                  if (!empty($filtered_indicator_id)) {
                    $index->trackItemsInserted($datasource_id, $filtered_indicator_id);
                  }
                }
              }
            }
          }
          else {
            $index->trackItemsInserted($datasource_id, $filtered_item_ids);
          }
        }
      }
      if ($updated_ids) {
        $filtered_item_ids = static::filterValidItemids($index, $datasource_id, $updated_ids);
        if ($filtered_item_ids && !empty($updated_translations)) {
          if ($entity->getType() == 'indicator') {
            foreach ($updated_translations as $updated_translation) {
              if ($entity->hasTranslation($updated_translation)) {
                $indicators_tracker_ids = $this->getIndicatorsTrackerIds($entity, $updated_translation);

                $existed_sdmx_indicators = [];
                foreach ($indicators as $indicator) {
                  $existed_sdmx_indicators[] = $this->prepareSearchApiEntityKey($entity, $updated_translation) . '_indicator_' . $indicator['id'];
                }

                // Diff between existed indicator.
                // if someone disappear we need to remove.
                $indicators_to_delete = array_diff($indicators_tracker_ids, $existed_sdmx_indicators);
                if (!empty($indicators_to_delete)) {
                  $index->trackItemsDeleted($datasource_id, $indicators_to_delete);
                }

                // All new indicators should be inserted.
                $indicators_to_insert = array_diff($existed_sdmx_indicators, $indicators_tracker_ids);
                if (!empty($indicators_to_insert)) {
                  $index->trackItemsInserted($datasource_id, $indicators_to_insert);
                }

                // Existed indicators should be updated.
                $indicators_to_update = array_intersect($indicators_tracker_ids, $existed_sdmx_indicators);
                if (!empty($indicators_to_update)) {
                  $index->trackItemsUpdated($datasource_id, $indicators_to_update);
                }
              }
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
          if ($entity->getType() == 'indicator') {
            foreach ($deleted_translations as $deleted_translation) {
              // Get items related to this indicator.
              // There could be difference between saved indicators and
              // news list from sdmx.
              // So just remove all that we have in tracker db table.
              $indicators_tracker_ids = $this->getIndicatorsTrackerIds($entity, $deleted_translation);

              $index->trackItemsDeleted($datasource_id, $indicators_tracker_ids);
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
      $indicators_ids = $this->getIndicatorsTrackerIds($entity);
      $datasource_id = 'entity:' . $entity->getEntityTypeId();

      foreach ($indexes as $index) {
        $index->trackItemsDeleted($datasource_id, $indicators_ids);
      }
    }
  }

  /**
   * Get indicators ids in tracker db table search item.
   */
  public function getIndicatorsTrackerIds(EntityInterface $indicator_entity, $langcode = NULL) {

    $indicator_entity_key = $this->prepareSearchApiEntityKey($indicator_entity, $langcode);
    return $this->database
      ->select('search_api_item', 'si')
      ->fields('si', ['item_id'])
      ->condition('si.item_id', $indicator_entity_key . '%', 'LIKE')
      ->execute()
      ->fetchCol();
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

  /**
   * Prepare key for search_api_item db table.
   */
  private function prepareSearchApiEntityKey($entity, $langcode = NULL) {
    if (!$langcode) {
      $langcode = $entity->language()->getId();
    }

    return 'entity:' . $entity->getEntityTypeId() . '/' . $entity->id() . ':' . $langcode;
  }

}
