<?php

namespace Drupal\quanthub_sdmx;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * The Manager for allowed content.
 *
 * Getting allowed datasets and storing to the cache default.
 */
class DatasetUrnStorage implements DatasetUrnStorageInterface {

  /**
   * The 15 minutes cache time.
   */
  const CACHE_TIME = 900;

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The prohibited datasets cache.
   *
   * @var array
   */
  private $datasets = [];

  /**
   * The service constructor.
   */
  public function __construct(
    protected QuanthubSdmxClientInterface $client,
    protected AccountProxyInterface $currentUser,
    protected CacheBackendInterface $cache,
    protected LanguageManagerInterface $language_manager,
    protected TimeInterface $time,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalDatasets(): array {
    $cid = 'quanthub_sdmx:local_datasets_list';
    if ($cache = $this->cache->get($cid)) {
      $datasets = $cache->data;
    }
    else {
      $datasets = [];
      $result = $this->nodeStorage->getAggregateQuery()
        ->accessCheck(FALSE)
        ->condition('status', 1)
        ->exists(self::URN_FIELD)
        ->groupby(self::URN_FIELD)
        ->execute();
      foreach ($result as $item) {
        if (!empty($item[self::URN_FIELD])) {
          $datasets[] = $item[self::URN_FIELD];
        }
      }

      $this->cache->set($cid, $datasets, tags: $this->nodeStorage->getEntityType()->getListCacheTags());
    }

    return $datasets;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedDatasets(): ?array {
    if (
      !$this->client->isConfigured() ||
      $this->currentUser->hasPermission('bypass dataset access')
    ) {
      return NULL;
    }

    // Check that dataset list is not already saved to cache.
    $cid = 'quanthub_sdmx:allowed_datasets_list:' . $this->currentUser->id();
    if ($cache = $this->cache->get($cid)) {
      $datasets = $cache->data;
    }
    else {
      $datasets = $this->client->getDatasetList() ?? [];

      // Support latest version.
      foreach ($datasets as $dataset) {
        $latest_dataset = preg_replace('/\(.+\)$/', '(~)', $dataset);
        if ($latest_dataset !== $dataset) {
          $datasets[] = $latest_dataset;
        }
      }

      // Update datasets in cache.
      $this->cache->set($cid, $datasets, $this->time->getCurrentTime() + $this::CACHE_TIME);
    }

    return $datasets;
  }

  /**
   * {@inheritdoc}
   */
  public function getProhibitedDatasets(): ?array {
    $allowed = $this->getAllowedDatasets();
    if ($allowed === NULL) {
      return NULL;
    }

    return array_diff($this->getLocalDatasets(), $allowed);
  }

}
