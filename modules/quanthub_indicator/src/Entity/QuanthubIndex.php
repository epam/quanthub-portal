<?php

namespace Drupal\quanthub_indicator\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Event\IndexingItemsEvent;
use Drupal\search_api\Event\ItemsIndexedEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\Utility;

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

  /**
   * {@inheritdoc}
   */
  public function loadItemsMultiple(array $item_ids) {
    // Group the requested items by datasource. This will also later be used to
    // determine whether all items were loaded successfully.
    $items_by_datasource = [];
    foreach ($item_ids as $item_id) {
      [$datasource_id, $raw_id] = Utility::splitCombinedId($item_id);
      $items_by_datasource[$datasource_id][$raw_id] = $item_id;
    }

    // Load the items from the datasources and keep track of which were
    // successfully retrieved.
    $items = [];
    foreach ($items_by_datasource as $datasource_id => $raw_ids) {
      try {
        $datasource = $this->getDatasource($datasource_id);
        $datasource_items = $datasource->loadMultiple(array_keys($raw_ids));
        foreach ($datasource_items as $raw_id => $item) {
          $id = $raw_ids[$raw_id];
          $items[$id] = $item;
          // Remember that we successfully loaded this item.
          unset($items_by_datasource[$datasource_id][$raw_id]);
        }
      }
      catch (SearchApiException $e) {
        $this->logException($e);
        // If the complete datasource could not be loaded, don't report all its
        // individual requested items as missing.
        unset($items_by_datasource[$datasource_id]);
      }
    }

    // Check whether there are requested items that couldn't be loaded.
    $items_by_datasource = array_filter($items_by_datasource);
    if ($items_by_datasource) {
      // Extract the second-level values of the two-dimensional array (that is,
      // the combined item IDs) and log a warning reporting their absence.
      $missing_ids = array_reduce(array_map('array_values', $items_by_datasource), 'array_merge', []);

      $filtered_missing_ids = [];
      foreach ($missing_ids as $missing_id) {
        if (!str_contains($missing_id, 'indicator')) {
          $filtered_missing_ids[] = $missing_id;
        }
      }

      if (!empty($filtered_missing_ids)) {
        $args['%index'] = $this->label();
        $args['@items'] = '"' . implode('", "', $filtered_missing_ids) . '"';
        $this->getLogger()
          ->warning('Could not load the following items on index %index: @items.', $args);
        // Also remove those items from tracking so we don't keep trying to load
        // them.
      }
    }
    foreach ($items_by_datasource as $datasource_id => $raw_ids) {
      $this->trackItemsDeleted($datasource_id, array_keys($raw_ids));
    }

    // Return the loaded items.
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsDeleted($datasource_id, array $ids) {
    if (!$this->status()) {
      return;
    }

    $item_ids = [];
    foreach ($ids as $id) {
      if (str_contains($id, 'indicator')) {
        $item_ids[] = $id;
      }
      else {
        $item_ids[] = Utility::createCombinedId($datasource_id, $id);
      }
    }
    if ($this->hasValidTracker()) {
      $this->getTrackerInstance()->trackItemsDeleted($item_ids);
    }
    if (!$this->isReadOnly() && $this->hasValidServer()) {
      $this->getServerInstance()->deleteItems($this, $item_ids);
    }
  }

  /**
   * Tracks insertion or updating of items.
   *
   * Used as a helper method in trackItemsInserted() and trackItemsUpdated() to
   * avoid code duplication.
   *
   * @param string $datasource_id
   *   The ID of the datasource to which the items belong.
   * @param array $ids
   *   An array of datasource-specific item IDs.
   * @param string $tracker_method
   *   The method to call on the tracker. Must be either "trackItemsInserted" or
   *   "trackItemsUpdated".
   */
  protected function trackItemsInsertedOrUpdated($datasource_id, array $ids, $tracker_method) {
    if ($this->hasValidTracker() && $this->status()) {
      $item_ids = [];
      foreach ($ids as $id) {
        if (str_contains($id, 'indicator')) {
          $item_ids[] = $id;
        }
        else {
          $item_ids[] = Utility::createCombinedId($datasource_id, $id);
        }
      }
      $this->getTrackerInstance()->$tracker_method($item_ids);
      if (!$this->isReadOnly() && $this->getOption('index_directly')
        && !$this->isBatchTracking()) {
        \Drupal::getContainer()->get('search_api.post_request_indexing')
          ->registerIndexingOperation($this->id(), $item_ids);
      }
    }
  }

}
