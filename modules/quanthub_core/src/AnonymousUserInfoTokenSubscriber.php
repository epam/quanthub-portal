<?php

namespace Drupal\quanthub_core;

use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Response subscriber to handle finished responses for the anonymous user.
 */
class AnonymousUserInfoTokenSubscriber implements EventSubscriberInterface, UserInfoInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The cache default service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The http client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs an AnonymousUserInfoTokenSubscriber object.
   */
  public function __construct(CacheBackendInterface $cache, AccountInterface $current_user, LoggerInterface $logger, ConfigFactoryInterface $configFactory, ClientInterface $http_client) {
    $this->cache = $cache;
    $this->currentUser = $current_user;
    $this->logger = $logger;
    $this->configFactory = $configFactory;
    $this->httpClient = $http_client;
  }

  /**
   * Get anonymous user info token and save to cache.
   *
   * As this token for anonymous user no sense to store this more secure.
   */
  public function onResponse(ResponseEvent $event) {
    if ($this->currentUser->isAnonymous() || $this->currentUser->id() == 1) {
      if (!$this->cache->get(self::ANONYMOUS_TOKEN_CID)) {
        // Oidc plugin id is dynamic hash, we firstly get id from oidc settings.
        $generic_realms = $this->configFactory->get('oidc.settings')->get('generic_realms');
        $oidc_plugin_id = array_shift($generic_realms);
        $oidc_plugin = $this->configFactory->get('oidc.realm.quanthub_b2c_realm.' . $oidc_plugin_id);
        $anonymous_endpoint = $oidc_plugin->get(self::ANONYMOUS_TOKEN_ENDPOINT);

        if ($anonymous_endpoint) {
          try {
            $response = $this->httpClient->get($anonymous_endpoint, [
              'headers' => [
                'Content-Type' => 'application/json',
              ],
            ]);

            $user_info_data = json_decode($response->getBody(), TRUE);
            $this->cache->set(self::ANONYMOUS_TOKEN_CID, $user_info_data['token'], strtotime($user_info_data['expiresOn']));
          }
          catch (RequestException $e) {
            $this->logger->error('Failed to retrieve tokens for anonymous user: @error.', [
              '@error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to retrieve the user info anonymous token', 0, $e);
          }
        }
        else {
          $this->logger->error('Failed to retrieve tokens for anonymous user: Anonymous token is not set');
        }

        $user_attributes_endpoint = $oidc_plugin->get(self::USER_ATTRIBUTES_ENDPOINT);
        if (!$this->cache->get(self::ANONYMOUS_QUANHUB_USER_ID)) {
          try {
            $response = $this->httpClient->get($user_attributes_endpoint, [
              'headers' => [
                'Authorization' => 'Bearer ' . $user_info_data['token'],
                'Content-Type' => 'application/json',
              ],
            ]);

            $user_attributes_data = json_decode($response->getBody(), TRUE);
            $this->cache->set(self::ANONYMOUS_QUANHUB_USER_ID, $user_attributes_data['userAttributes']['USER_ID'], strtotime($user_info_data['expiresOn']));
          }
          catch (RequestException $e) {
            $this->logger->error('Failed to retrieve quanthub user id for anonymous user: @error.', [
              '@error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to retrieve quanthub user id for anonymous token', 0, $e);
          }
        }
      }
    }
  }

  /**
   * Subscribe to kernel response event.
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onResponse', 30];
    return $events;
  }

}
