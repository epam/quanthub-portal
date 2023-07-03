<?php

namespace Drupal\quanthub_core;

use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oidc\OpenidConnectSessionInterface;
use Drupal\user\UserDataInterface;
use GuzzleHttp\Exception\RequestException;
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
   * Get token for anonymous from cache and authenticated user from oidc plugin.
   */
  public function getToken() {
    // For anonymous and admin we will use anonymous token.
    if ($this->currentUser->isAnonymous() || $this->currentUser->id() == 1) {
      if (!$this->cache->get(self::ANONYMOUS_TOKEN_CID)) {
        $this->updateAnonymousToken();
      }
      $token = $this->cache->get(self::ANONYMOUS_TOKEN_CID)->data;
    }
    else {
      $token = $this->openidConnectSession->getJsonWebTokens()->getAccessToken()->getValue();
    }

    return $token;
  }

  /**
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
    $oidc_plugin = $this->configFactory->get('oidc.realm.quanthub_b2c_realm.' . $oidc_plugin_id);
    if (!isset($oidc_plugin)) {
      return;
    }
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
    if (!$this->cache->get(self::ANONYMOUS_QUANTHUB_USER_ID) && !empty($user_info_data['token']) && !empty($user_info_data['expiresOn'])) {
      try {
        $response = $this->httpClient->get($user_attributes_endpoint, [
          'headers' => [
            'Authorization' => 'Bearer ' . $user_info_data['token'],
            'Content-Type' => 'application/json',
          ],
        ]);

        $user_attributes_data = json_decode($response->getBody(), TRUE);
        $this->cache->set(self::ANONYMOUS_QUANTHUB_USER_ID, $user_attributes_data['userAttributes']['USER_ID'], strtotime($user_info_data['expiresOn']));
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
