<?php

namespace Drupal\quanthub_core;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Component\Datetime\Time;

/**
 * The Manager for allowed content.
 *
 * Getting from xacml_soap_client and storing to the cache default.
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
   * The http client for wso2 request.
   *
   * @var \Drupal\quanthub_core\XacmlSoapClient
   */
  protected $xacmlSoapClient;

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
    XacmlSoapClient $xacml_soap_client,
    AccountProxy $current_user,
    CacheBackendInterface $cache,
    LanguageManager $language_manager,
    Time $time,
  ) {
    $this->xacmlSoapClient = $xacml_soap_client;
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

      // Update datasets in cache.
      if ($this->datasets) {
        $this->cache->set(
          $this->getCacheCid(),
          $this->datasets,
          $this->time->getCurrentTime() + $this::CACHE_TIME
        );

        // Invalidating cache tags for updating views
        // with datasets and publications.
        Cache::invalidateTags(['allowed_content_tag:' . $this->currentUser->id()]);
      }
    }
    // For admin show all content related to datasets.
    if ($this->currentUser->id() == 1) {
      return [];
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
   * Get User Dataset List from user data.
   */
  public function getUserDatasetList() {
    return $this->xacmlSoapClient->getDatasetList();
  }

}
