<?php

namespace Drupal\quanthub_core;

use Drupal\Component\Datetime\Time;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Session\AccountProxy;

/**
 * The Manager for allowed content.
 *
 * Getting allowed datasets and storing to the cache default.
 */
class AllowedContentManager implements QuanthubCoreInterface {

  /**
   * Name of user data argument for datasets.
   */
  const USER_DATA_DATASETS = 'allowed_datasets';

  /**
   * The 15 minutes cache time.
   */
  const CACHE_TIME = 900;

  /**
   * The (lazy loaded) dependency injection (DI) container.
   *
   * @var ?\Drupal\Component\DependencyInjection\ContainerInterface
   */
  protected ?ContainerInterface $container;

  /**
   * The (lazy loaded) SDMX client.
   *
   * @var ?\Drupal\quanthub_core\QuanthubSdmxClient
   */
  protected ?QuanthubSdmxClient $quanthubSdmxClient;

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
   * Dataset List.
   *
   * @var array
   */
  protected $datasets = [];

  /**
   * {@inheritDoc}
   */
  public function __construct(
    AccountProxy $current_user,
    CacheBackendInterface $cache,
    LanguageManager $language_manager,
    Time $time,
  ) {
    $this->currentUser = $current_user;
    $this->cache = $cache;
    $this->languageManager = $language_manager;
    $this->time = $time;
  }

  /**
   * Get Datasets IDs from cache if not existed updated this by request to wso2.
   */
  public function getAllowedDatasetList() {
    // Check that user authenticated and is not admin.
    // Check that dataset list is not already saved to cache.
    if ($cache = $this->cache->get($this->getCacheCid())) {
      if (!empty($cache->data)) {
        $this->datasets = $cache->data;
      }
      else {
        $this->datasets = [];
      }
    }
    else {
      $this->datasets = $this->getUserDatasetList();

      // Support latest version.
      foreach ($this->datasets as $dataset) {
        $latest_dataset = preg_replace('/\(.+\)$/', '(~)', $dataset);
        if ($latest_dataset !== $dataset) {
          $this->datasets[] = $latest_dataset;
        }
      }

      // Update datasets in cache.
      $this->cache->set(
        $this->getCacheCid(),
        $this->datasets,
        $this->time->getCurrentTime() + $this::CACHE_TIME
      );

      // Invalidating cache tags for updating views
      // with datasets and publications.
      Cache::invalidateTags(['allowed_content_tag:' . $this->currentUser->id()]);
    }

    return $this->datasets;
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
    return $this->quanthubSdmxClient()->getDatasetList();
  }

  /**
   * Get Dependency Injection container.
   *
   * @return \Drupal\Component\DependencyInjection\ContainerInterface
   *   Current Dependency Injection container.
   */
  protected function getContainer(): ContainerInterface {
    if (!isset($this->container)) {
      $this->container = quanthub_core_container();
    }
    return $this->container;
  }

  /**
   * Get lazy-loaded quanthub_sdmx_sync service.
   *
   * @return \Drupal\quanthub_core\QuanthubSdmxClient
   *   QuanthubSdmxClient service.
   */
  protected function quanthubSdmxClient(): QuanthubSdmxClient {
    if (!isset($this->quanthubSdmxClient)) {
      $this->quanthubSdmxClient = $this->getContainer()->get('sdmx_client');
    }
    return $this->quanthubSdmxClient;
  }

}
