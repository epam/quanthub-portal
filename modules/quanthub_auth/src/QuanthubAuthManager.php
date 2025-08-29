<?php

namespace Drupal\quanthub_auth;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\quanthub_auth\Annotation\QuanthubAuth;

/**
 * QuanthubAuth plugin manager.
 */
final class QuanthubAuthManager extends DefaultPluginManager implements QuanthubAuthInterface {

  /**
   * Static cache.
   */
  protected array $cache = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected AccountInterface $currentUser,
  ) {
    parent::__construct('Plugin/QuanthubAuth', $namespaces, $module_handler, QuanthubAuthPluginInterface::class, QuanthubAuth::class);
    $this->alterInfo('quanthub_auth_info');
    $this->setCacheBackend($cache_backend, 'quanthub_auth_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getUserToken(): ?string {
    if ($this->currentUser->isAnonymous()) {
      return $this->getAnonymousToken();
    }
    if (!array_key_exists(QuanthubAuthPluginInterface::AUTHENTICATED, $this->cache)) {
      // Token value is not cached to allow the plugin refresh it if needed.
      $this->cache[QuanthubAuthPluginInterface::AUTHENTICATED] = $this->findToken(QuanthubAuthPluginInterface::AUTHENTICATED);
    }

    return $this->cache[QuanthubAuthPluginInterface::AUTHENTICATED]?->getToken();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnonymousToken(): ?string {
    if (!array_key_exists(QuanthubAuthPluginInterface::ANONYMOUS, $this->cache)) {
      // Token value is not cached to allow the plugin refresh it if needed.
      $this->cache[QuanthubAuthPluginInterface::ANONYMOUS] = $this->findToken(QuanthubAuthPluginInterface::ANONYMOUS);
    }
    return $this->cache[QuanthubAuthPluginInterface::ANONYMOUS]?->getToken();
  }

  /**
   * Helper to find proper plugin.
   */
  protected function findToken($scope): ?QuanthubAuthPluginInterface {
    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      if ($definition['scope'] !== $scope) {
        continue;
      }

      /** @var \Drupal\quanthub_auth\QuanthubAuthPluginInterface $instance */
      $instance = $this->createInstance($plugin_id);
      if ($instance->getToken()) {
        return $instance;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = parent::findDefinitions();
    uasort($definitions, function ($a, $b) {
      return (int) $b['priority'] <=> (int) $a['priority'];
    });
    return $definitions;
  }

}
