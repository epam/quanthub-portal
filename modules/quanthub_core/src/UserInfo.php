<?php

namespace Drupal\quanthub_core;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oidc\OpenidConnectSessionInterface;
use Drupal\user\UserDataInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * The service for getting user info token and user attributes.
 *
 * This service works for anonymous and authorised user.
 */
class UserInfo implements UserInfoInterface {

  /**
   * The open id connect session service.
   *
   * @var \Drupal\oidc\OpenidConnectSessionInterface
   */
  protected $openidConnectSession;

  /**
   * The cache default service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The http client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs an AnonymousUserInfoTokenSubscriber object.
   */
  public function __construct(AccountInterface $current_user, CacheBackendInterface $cache, OpenidConnectSessionInterface $openid_connect_session, UserDataInterface $user_data, ClientInterface $http_client, LoggerInterface $logger, ConfigFactoryInterface $configFactory) {
    $this->currentUser = $current_user;
    $this->cache = $cache;
    $this->openidConnectSession = $openid_connect_session;
    $this->userData = $user_data;
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->configFactory = $configFactory;
  }

  /**
   * Get token for user from cache.
   */
  public function getToken() {
    if (!$this->cache->get(self::ANONYMOUS_TOKEN_CID)) {
      $this->updateAnonymousToken();
    }
    $cached_item = $this->cache->get(self::ANONYMOUS_TOKEN_CID);

    return $cached_item ? $cached_item->data : NULL;
  }

  /**
   * Return quanthub user id.
   *
   * @deprecated. Authenticated token is deprecated and will be deleted soon.
   * Get QuantHub User Id for anonymous and authenticated user.
   *
   * For anonymous from cache and authenticated user from user data.
   */
  public function getQuanthubUserId() {
    if ($this->currentUser->isAnonymous() || $this->currentUser->id() == 1) {
      $quanthub_user_id = reset($this->cache->get(self::ANONYMOUS_QUANTHUB_USER_ID)->data);
    }
    else {
      $quanthub_user_id = $this->userData->get(
        self::MODULE_NAME,
        $this->currentUser->id(),
        self::USER_QUANTHUB_ID
      );
    }

    return $quanthub_user_id;
  }

  /**
   * Get user info role.
   *
   * @deprecated. Authenticated token is deprecated and will be deleted soon.
   * Get Quanthub User Role.
   */
  public function getUserInfoRole() {
    if ($this->currentUser->isAnonymous()) {
      return [self::QUANTHUB_ANONYMOUS_ROLE];
    }
    else {
      return $this->userData->get(
        self::MODULE_NAME,
        $this->currentUser->id(),
        self::USER_QUANTHUB_ROLE
      );
    }
  }

  /**
   * Get user info groups.
   *
   * @deprecated. Authenticated token is deprecated and will be deleted soon.
   * Get Quanthub User Groups.
   */
  public function getUserInfoGroups() {
    if (!$this->currentUser->isAnonymous()) {
      return $this->userData->get(
        self::MODULE_NAME,
        $this->currentUser->id(),
        self::USER_QUANTHUB_GROUPS
      );
    }
  }

  /**
   * Update user info anonymous token and save to the cache.
   *
   * As this token for anonymous user no sense to store this more secure.
   */
  public function updateAnonymousToken() {
    // Oidc plugin id is dynamic hash, we firstly get id from oidc settings.
    $generic_realms = $this->configFactory->get('oidc.settings')->get('generic_realms');
    if (count($generic_realms) == 0) {
      return;
    }

    $oidc_plugin_id = array_shift($generic_realms);
    $configName = 'oidc.realm.quanthub_b2c_realm.' . $oidc_plugin_id;
    $oidc_plugin = $this->configFactory->get($configName);
    if ($oidc_plugin->isNew()) {
      $this->logger->error('Config @config does not exist', ['@config' => $configName]);
      return;
    }

    $anonymous_endpoint = $oidc_plugin->get(self::ANONYMOUS_TOKEN_ENDPOINT);
    if ($anonymous_endpoint) {
      try {
        $response = $this->httpClient->get($anonymous_endpoint, [
          'headers' => [
            'Content-Type' => 'application/json',
          ],
          'timeout' => 10,
          'connect_timeout' => 10,
        ]);

        $user_info_data = json_decode($response->getBody(), TRUE);
        $this->cache->set(self::ANONYMOUS_TOKEN_CID, $user_info_data['token'], strtotime($user_info_data['expiresOn']));
      }
      catch (\Exception $e) {
        $this->logger->error('Failed to retrieve tokens for anonymous user: @error.', [
          '@error' => $e->getMessage(),
        ]);
      }
    }
    else {
      $this->logger->error('Failed to retrieve tokens for anonymous user: Anonymous endpoint is not set');
    }

  }

}
