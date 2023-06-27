<?php

namespace Drupal\quanthub_core\Plugin\views\cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\cache\Tag;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cache plugin provides cache tag for updating based on allowed datasets list.
 *
 * @ingroup views_cache_plugins
 *
 * @ViewsCache(
 *   id = "allowed_content_tag",
 *   title = @Translation("Allowed Content tag - Quanthub"),
 *   help = @Translation("Adding cache tag for updating based on allowed datasets list.")
 * )
 */
class AllowedContentTag extends Tag {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Constructs a PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(
      parent::getCacheTags(),
      ['allowed_content_tag:' . $this->currentUser->id()]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterCacheMetadata(CacheableMetadata $cache_metadata) {
    $cache_metadata->addCacheContexts(['user_info_attributes']);
  }

}
