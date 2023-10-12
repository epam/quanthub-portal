<?php

namespace Drupal\quanthub_indicator\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Event\IndexingItemsEvent;
use Drupal\search_api\Event\ItemsIndexedEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiException;

/**
 * Defines the search index configuration entity.
 *
 * @ConfigEntityType(
 *   id = "search_api_index",
 *   label = @Translation("Search index"),
 *   label_collection = @Translation("Search indexes"),
 *   label_singular = @Translation("search index"),
 *   label_plural = @Translation("search indexes"),
 *   label_count = @PluralTranslation(
 *     singular = "@count search index",
 *     plural = "@count search indexes",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\search_api\Entity\SearchApiConfigEntityStorage",
 *     "list_builder" = "Drupal\search_api\IndexListBuilder",
 *     "form" = {
 *       "default" = "Drupal\search_api\Form\IndexForm",
 *       "edit" = "Drupal\search_api\Form\IndexForm",
 *       "fields" = "Drupal\search_api\Form\IndexFieldsForm",
 *       "add_fields" = "Drupal\search_api\Form\IndexAddFieldsForm",
 *       "field_config" = "Drupal\search_api\Form\FieldConfigurationForm",
 *       "break_lock" = "Drupal\search_api\Form\IndexBreakLockForm",
 *       "processors" = "Drupal\search_api\Form\IndexProcessorsForm",
 *       "delete" = "Drupal\search_api\Form\IndexDeleteConfirmForm",
 *       "disable" = "Drupal\search_api\Form\IndexDisableConfirmForm",
 *       "reindex" = "Drupal\search_api\Form\IndexReindexConfirmForm",
 *       "clear" = "Drupal\search_api\Form\IndexClearConfirmForm",
 *       "rebuild_tracker" = "Drupal\search_api\Form\IndexRebuildTrackerConfirmForm",
 *     },
 *   },
 *   admin_permission = "administer search_api",
 *   config_prefix = "index",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *   },
 *   config_export = {
 *     "id",
 *     "name",
 *     "description",
 *     "read_only",
 *     "field_settings",
 *     "datasource_settings",
 *     "processor_settings",
 *     "tracker_settings",
 *     "options",
 *     "server",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/search-api/index/{search_api_index}",
 *     "add-form" = "/admin/config/search/search-api/add-index",
 *     "edit-form" = "/admin/config/search/search-api/index/{search_api_index}/edit",
 *     "fields" = "/admin/config/search/search-api/index/{search_api_index}/fields",
 *     "add-fields" = "/admin/config/search/search-api/index/{search_api_index}/fields/add/nojs",
 *     "add-fields-ajax" = "/admin/config/search/search-api/index/{search_api_index}/fields/add/ajax",
 *     "break-lock-form" = "/admin/config/search/search-api/index/{search_api_index}/fields/break-lock",
 *     "processors" = "/admin/config/search/search-api/index/{search_api_index}/processors",
 *     "delete-form" = "/admin/config/search/search-api/index/{search_api_index}/delete",
 *     "disable" = "/admin/config/search/search-api/index/{search_api_index}/disable",
 *     "enable" = "/admin/config/search/search-api/index/{search_api_index}/enable",
 *   }
 * )
 */
class QuanthubIndex extends Index {

  /**
   * {@inheritdoc}
   */
  public function indexSpecificItems(array $search_objects) {
    if (!$search_objects || $this->read_only) {
      return [];
    }
    if (!$this->status) {
      $index_label = $this->label();
      throw new SearchApiException("Couldn't index values on index '$index_label' (index is disabled)");
    }

    /** @var \Drupal\search_api\Item\ItemInterface[] $items */
    $items = [];
    foreach ($search_objects as $item_id => $object) {
      $items[$item_id] = \Drupal::getContainer()
        ->get('search_api.fields_helper')
        ->createItemFromObject($this, $object, $item_id);

      // Logic for indicator indexing (multiple search items for one node).
      if (str_contains($item_id, '_indicator_')) {
        $parts = explode('_indicator_', $item_id);
        if (!empty($parts[1])) {
          $items[$item_id]->setExtraData('indicator_id', $parts[1]);
        }
      }
    }

    // Remember the items that were initially passed, to be able to determine
    // the items rejected by alter hooks and processors afterwards.
    $rejected_ids = array_keys($items);
    $rejected_ids = array_combine($rejected_ids, $rejected_ids);

    // Preprocess the indexed items.
    $this->alterIndexedItems($items);
    $description = 'This hook is deprecated in search_api:8.x-1.14 and is removed from search_api:2.0.0. Please use the "search_api.indexing_items" event instead. See https://www.drupal.org/node/3059866';
    \Drupal::moduleHandler()->alterDeprecated($description, 'search_api_index_items', $this, $items);
    $event = new IndexingItemsEvent($this, $items);
    \Drupal::getContainer()->get('event_dispatcher')
      ->dispatch($event, SearchApiEvents::INDEXING_ITEMS);
    $items = $event->getItems();
    foreach ($items as $item) {
      // This will cache the extracted fields so processors, etc., can retrieve
      // them directly.
      $item->getFields();
    }
    $this->preprocessIndexItems($items);

    // Remove all items still in $items from $rejected_ids. Thus, only the
    // rejected items' IDs are still contained in $ret, to later be returned
    // along with the successfully indexed ones.
    foreach ($items as $item_id => $item) {
      unset($rejected_ids[$item_id]);
    }

    // Items that are rejected should also be deleted from the server.
    if ($rejected_ids) {
      $this->getServerInstance()->deleteItems($this, $rejected_ids);
    }

    $indexed_ids = [];
    if ($items) {
      $indexed_ids = $this->getServerInstance()->indexItems($this, $items);
    }

    // Return the IDs of all items that were either successfully indexed or
    // rejected before being handed to the server.
    $processed_ids = array_merge(array_values($rejected_ids), array_values($indexed_ids));

    if ($processed_ids) {
      if ($this->hasValidTracker()) {
        $this->getTrackerInstance()->trackItemsIndexed($processed_ids);
      }
      // Since we've indexed items now, triggering reindexing would have some
      // effect again. Therefore, we reset the flag.
      $this->setHasReindexed(FALSE);

      $description = 'This hook is deprecated in search_api:8.x-1.14 and is removed from search_api:2.0.0. Please use the "search_api.items_indexed" event instead. See https://www.drupal.org/node/3059866';
      \Drupal::moduleHandler()->invokeAllDeprecated(
        $description,
        'search_api_items_indexed',
        [$this, $processed_ids]
      );

      $dispatcher = \Drupal::getContainer()->get('event_dispatcher');
      $dispatcher->dispatch(new ItemsIndexedEvent($this, $processed_ids), SearchApiEvents::ITEMS_INDEXED);

      // Clear search api list caches.
      Cache::invalidateTags(['search_api_list:' . $this->id]);
    }

    // When indexing via Drush, multiple iterations of a batch will happen in
    // the same PHP process, so the static cache will quickly fill up. To
    // prevent this, clear it after each batch of items gets indexed.
    if (function_exists('drush_backend_batch_process') && batch_get()) {
      \Drupal::getContainer()->get('entity.memory_cache')->deleteAll();
    }

    return $processed_ids;
  }

}
