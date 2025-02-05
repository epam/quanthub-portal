<?php

namespace Drupal\quanthub_core;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Session\AccountProxy;

/**
 * The Manager for allowed content.
 *
 * Getting allowed datasets and storing to the cache default.
 */
class AllowedContentManager implements QuanthubCoreInterface {

  /**
   * Dataset URN field name.
   */
  const URN_FIELD = 'field_quanthub_urn';

  /**
   * The 15 minutes cache time.
   */
  const CACHE_TIME = 900;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The cache default service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

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
   * {@inheritDoc}
   */
  public function __construct(
    AccountProxy $current_user,
    CacheBackendInterface $cache,
    LanguageManager $language_manager,
    Time $time,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->currentUser = $current_user;
    $this->cache = $cache;
    $this->languageManager = $language_manager;
    $this->time = $time;
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * Get Datasets IDs from cache if not existed updated this by request to wso2.
   */
  public function getAllowedDatasetList() {
    // Check that user authenticated and is not admin.
    // Check that dataset list is not already saved to cache.
    if ($cache = $this->cache->get($this->getCacheCid())) {
      if (!empty($cache->data)) {
        $datasets = $cache->data;
      }
      else {
        $datasets = [];
      }
    }
    else {
      $datasets = $this->getUserDatasetList();

      // Support latest version.
      foreach ($datasets as $dataset) {
        $latest_dataset = preg_replace('/\(.+\)$/', '(~)', $dataset);
        if ($latest_dataset !== $dataset) {
          $datasets[] = $latest_dataset;
        }
      }

      // Update datasets in cache.
      $this->cache->set(
        $this->getCacheCid(),
        $datasets,
        $this->time->getCurrentTime() + $this::CACHE_TIME
      );
    }

    return $datasets;
  }

  /**
   * Gets prohibited datasets list.
   *
   * The list is calculated from DB data based on URN default field
   *   or user specified one.
   */
  public function getProhibitedDatasetList($field_name = self::URN_FIELD) {
    if (!isset($this->datasets[$field_name])) {
      $datasets = [];
      $result = $this->nodeStorage->getAggregateQuery()
        ->accessCheck(FALSE)
        ->condition('status', 1)
        ->exists($field_name)
        ->groupby($field_name)
        ->execute();
      foreach ($result as $item) {
        if (!empty($item[$field_name])) {
          $datasets[] = $item[$field_name];
        }
      }

      $this->datasets[$field_name] = array_diff($datasets, $this->getAllowedDatasetList());
    }

    return $this->datasets[$field_name];
  }

  /**
   * Get cache cid.
   *
   * @return string
   *   Cid string for cache.
   */
  public function getCacheCid() {
    return $this::MODULE_NAME . ':allowed_datasets_list:' . $this->currentUser->id();
  }

  /**
   * Get User's Dataset List.
   */
  public function getUserDatasetList() {
    return self::quanthubSdmxClient()->getDatasetList();
  }

  /**
   * Get lazy-loaded quanthub_sdmx_sync service.
   *
   * @return \Drupal\quanthub_core\QuanthubSdmxClient
   *   QuanthubSdmxClient service.
   */
  protected static function quanthubSdmxClient(): QuanthubSdmxClient {
    return \Drupal::service('sdmx_client');
  }

}
