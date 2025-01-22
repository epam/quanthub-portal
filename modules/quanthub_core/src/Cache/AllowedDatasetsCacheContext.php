<?php

namespace Drupal\quanthub_core\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Cache\Context\UserCacheContextBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\quanthub_core\AllowedContentManager;

/**
 * Defines the User Allowed Datasets cache context service.
 *
 * Cache context ID: 'user.datasets'.
 */
class AllowedDatasetsCacheContext extends UserCacheContextBase implements CacheContextInterface {

  /**
   * The Allowed Content Manager service.
   *
   * @var \Drupal\quanthub_core\AllowedContentManager
   */
  protected $allowedContentManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountInterface $user, AllowedContentManager $allowed_content_manager) {
    parent::__construct($user);

    $this->allowedContentManager = $allowed_content_manager;
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
    $datasets = NULL;
    if (getenv('WSO_IGNORE') !== 'TRUE') {
      $datasets = $this->allowedContentManager->getAllowedDatasetList();
      sort($datasets);
    }
    // We don't need to secure this information, crc32 is enough.
    return hash('crc32', serialize($datasets));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return (new CacheableMetadata())->setCacheTags(['user:' . $this->user->id()]);
  }

}
