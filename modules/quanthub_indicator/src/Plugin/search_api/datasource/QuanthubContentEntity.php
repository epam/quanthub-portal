<?php

namespace Drupal\quanthub_indicator\Plugin\search_api\datasource;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\quanthub_sdmx_sync\QuanthubSdmxClient;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Override search api content entity datasource plugin.
 *
 * @SearchApiDatasource(
 *   id = "entity",
 *   deriver = "Drupal\search_api\Plugin\search_api\datasource\ContentEntityDeriver"
 * )
 */
class QuanthubContentEntity extends ContentEntity {

  /**
   * The SDMX client.
   *
   * @var \Drupal\quanthub_sdmx_sync\QuanthubSdmxClient
   */
  protected $sdmxClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $datasource */
    $datasource = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $datasource->setSdmxClient($container->get('sdmx_client'));

    return $datasource;
  }

  /**
   * {@inheritdoc}
   */
  public function setSdmxClient(QuanthubSdmxClient $sdmxClient) {
    $this->sdmxClient = $sdmxClient;
  }

  /**
   * {@inheritdoc}
   */
  public function getPartialItemIds($page = NULL, array $bundles = NULL, array $languages = NULL) {
    // These would be pretty pointless calls, but for the sake of completeness
    // we should check for them and return early. (Otherwise makes the rest of
    // the code more complicated.)
    if (($bundles === [] && !$languages) || ($languages === [] && !$bundles)) {
      return NULL;
    }

    $entity_type = $this->getEntityType();
    $entity_id = $entity_type->getKey('id');

    // Use a direct database query when an entity has a defined base table. This
    // should prevent performance issues associated with the use of entity query
    // on large data sets. This allows for better control over what tables are
    // included in the query.
    // If no base table is present, then perform an entity query instead.
    if ($entity_type->getBaseTable()
      && empty($this->configuration['disable_db_tracking'])) {
      $select = $this->getDatabaseConnection()
        ->select($entity_type->getBaseTable(), 'base_table')
        ->fields('base_table', [$entity_id]);
    }
    else {
      $select = $this->getEntityTypeManager()
        ->getStorage($this->getEntityTypeId())
        ->getQuery();
      // When tracking items, we never want access checks.
      $select->accessCheck(FALSE);
    }

    // Build up the context for tracking the last ID for this batch page.
    $batch_page_context = [
      'index_id' => $this->getIndex()->id(),
      // The derivative plugin ID includes the entity type ID.
      'datasource_id' => $this->getPluginId(),
      'bundles' => $bundles,
      'languages' => $languages,
    ];
    $context_key = Crypt::hashBase64(serialize($batch_page_context));
    $last_ids = $this->getState()->get(self::TRACKING_PAGE_STATE_KEY, []);

    // We want to determine all entities of either one of the given bundles OR
    // one of the given languages. That means we can't just filter for $bundles
    // if $languages is given. Instead, we have to filter for all bundles we
    // might want to include and later sort out those for which we want only the
    // translations in $languages and those (matching $bundles) where we want
    // all (enabled) translations.
    if ($this->hasBundles()) {
      $bundle_property = $entity_type->getKey('bundle');
      if ($bundles && !$languages) {
        $select->condition($bundle_property, $bundles, 'IN');
      }
      else {
        $enabled_bundles = array_keys($this->getBundles());
        // Since this is also called for removed bundles/languages,
        // $enabled_bundles might not include $bundles.
        if ($bundles) {
          $enabled_bundles = array_unique(array_merge($bundles, $enabled_bundles));
        }
        if (count($enabled_bundles) < count($this->getEntityBundles())) {
          $select->condition($bundle_property, $enabled_bundles, 'IN');
        }
      }
    }

    if (isset($page)) {
      $page_size = $this->getConfigValue('tracking_page_size');
      assert($page_size, 'Tracking page size is not set.');

      // If known, use a condition on the last tracked ID for paging instead of
      // the offset, for performance reasons on large sites.
      $offset = $page * $page_size;
      if ($page > 0) {
        // We only handle the case of picking up from where the last page left
        // off. (This will cause an infinite loop if anyone ever wants to index
        // Search API tasks in an index, so check for that to be on the safe
        // side.)
        if (isset($last_ids[$context_key])
          && $last_ids[$context_key]['page'] == ($page - 1)
          && $this->getEntityTypeId() !== 'search_api_task') {
          $select->condition($entity_id, $last_ids[$context_key]['last_id'], '>');
          $offset = 0;
        }
      }
      $select->range($offset, $page_size);

      // For paging to reliably work, a sort should be present.
      if ($select instanceof SelectInterface) {
        $select->orderBy($entity_id);
      }
      else {
        $select->sort($entity_id);
      }
    }

    if ($select instanceof SelectInterface) {
      $entity_ids = $select->execute()->fetchCol();
    }
    else {
      $entity_ids = $select->execute();
    }

    if (!$entity_ids) {
      if (isset($page)) {
        // Clean up state tracking of last ID.
        unset($last_ids[$context_key]);
        $this->getState()->set(self::TRACKING_PAGE_STATE_KEY, $last_ids);
      }
      return NULL;
    }

    // Remember the last tracked ID for the next call.
    if (isset($page)) {
      $last_ids[$context_key] = [
        'page' => (int) $page,
        'last_id' => end($entity_ids),
      ];
      $this->getState()->set(self::TRACKING_PAGE_STATE_KEY, $last_ids);
    }

    // For all loaded entities, compute all their item IDs (one for each
    // translation we want to include). For those matching the given bundles (if
    // any), we want to include translations for all enabled languages. For all
    // other entities, we just want to include the translations for the
    // languages passed to the method (if any).
    $item_ids = [];
    $enabled_languages = array_keys($this->getLanguages());
    // As above for bundles, $enabled_languages might not include $languages.
    if ($languages) {
      $enabled_languages = array_unique(array_merge($languages, $enabled_languages));
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    foreach ($this->getEntityStorage()->loadMultiple($entity_ids) as $entity_id => $entity) {
      $translations = array_keys($entity->getTranslationLanguages());
      $translations = array_intersect($translations, $enabled_languages);
      // If only languages were specified, keep only those translations matching
      // them. If bundles were also specified, keep all (enabled) translations
      // for those entities that match those bundles.
      if ($languages !== NULL
        && (!$bundles || !in_array($entity->bundle(), $bundles))) {
        $translations = array_intersect($translations, $languages);
      }

      if ($entity->getType() == 'indicator') {

        $dataset_urn = $entity
          ->field_dataset
          ->first()
          ->entity
          ->field_quanthub_urn
          ->getString();

        $dimension_id = $entity->field_indicator_parameter->getString();
        $indicators = $this->sdmxClient->datasetIndicators($dataset_urn, $dimension_id);
      }

      foreach ($translations as $langcode) {
        if ($entity->getType() == 'indicator') {
          if (!empty($indicators) && is_array($indicators)) {
            foreach (array_keys($indicators) as $indicator_id) {
              $item_ids[] = "$entity_id:$langcode" . '_indicator_' . $indicator_id;
            }
          }
        } else {
          $item_ids[] = "$entity_id:$langcode";
        }
      }
    }

    if (Utility::isRunningInCli()) {
      // When running in the CLI, this might be executed for all entities from
      // within a single process. To avoid running out of memory, reset the
      // static cache after each batch.
      $this->getEntityMemoryCache()->deleteAll();
    }
    return $item_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    $allowed_languages = $this->getLanguages();

    $entity_ids = [];
    foreach ($ids as $item_id) {
      $indicator_node_flag = FALSE;
      $pos = strrpos($item_id, ':');
      // This can only happen if someone passes an invalid ID, since we always
      // include a language code. Still, no harm in guarding against bad input.
      if ($pos === FALSE) {
        continue;
      }
      $entity_id = substr($item_id, 0, $pos);
      $langcode = substr($item_id, $pos + 1);
      if (str_contains($langcode, '_indicator')) {
        $exploded_langcode = explode('_indicator', $langcode);
        if (!empty($exploded_langcode[0])) {
          $langcode = $exploded_langcode[0];
        }
        $indicator_node_flag = TRUE;
      }
      if (isset($allowed_languages[$langcode])) {
        if ($indicator_node_flag) {
          $entity_ids[$entity_id][$item_id] = $langcode . '_indicator' . $exploded_langcode[1];
        }
        else {
          $entity_ids[$entity_id][$item_id] = $langcode;
        }
      }
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
    $entities = $this->getEntityStorage()->loadMultiple(array_keys($entity_ids));
    $items = [];
    $allowed_bundles = $this->getBundles();

    foreach ($entity_ids as $entity_id => $langcodes) {
      if (empty($entities[$entity_id]) || !isset($allowed_bundles[$entities[$entity_id]->bundle()])) {
        continue;
      }
      foreach ($langcodes as $item_id => $langcode) {

        $filtered_langcode = $langcode;
        if (str_contains($langcode, '_indicator')) {
          $exploded_langcode = explode('_indicator', $langcode);
          if (!empty($exploded_langcode[0])) {
            $filtered_langcode = $exploded_langcode[0];
          }
        }

        if ($entities[$entity_id]->hasTranslation($filtered_langcode)) {
          $items[$item_id] = $entities[$entity_id]->getTranslation($filtered_langcode)->getTypedData();
        }
      }
    }

    return $items;
  }

}
