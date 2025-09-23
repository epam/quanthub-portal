<?php

namespace Drupal\quanthub_sdmx\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Cache\Context\UserCacheContextBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\quanthub_sdmx\DatasetUrnStorageInterface;

/**
 * Defines the User Allowed Datasets cache context service.
 *
 * Cache context ID: 'user.datasets'.
 */
class AllowedDatasetsCacheContext extends UserCacheContextBase implements CacheContextInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    AccountInterface $user,
    protected DatasetUrnStorageInterface $urnStorage,
  ) {
    parent::__construct($user);
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Allowed Datasets');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    // We don't need to secure this information, crc32 is enough.
    return hash('crc32', serialize($this->urnStorage->getAllowedDatasets()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
