<?php

namespace Drupal\quanthub_core\Plugin\views\cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\views\Plugin\views\cache\Tag;

/**
 * Cache plugin to work with custom user info cache context and max age 15 mins.
 *
 * @ingroup views_cache_plugins
 *
 * @ViewsCache(
 *   id = "user_info_cache_context",
 *   title = @Translation("Tag and user info context based - Quanthub"),
 *   help = @Translation("Tag and user info context based caching of data. Caches will persist until any related cache tags are invalidated.")
 * )
 */
class UserInfoCacheContext extends Tag {

  /**
   * Alters the cache metadata of a display upon saving a view.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The cache metadata.
   */
  public function alterCacheMetadata(CacheableMetadata $cache_metadata) {
    $cache_metadata->addCacheContexts(['user_info_attributes']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // 15 mins cache max age.
    return 900;
  }

}
