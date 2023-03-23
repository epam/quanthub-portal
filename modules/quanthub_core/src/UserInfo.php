<?php

namespace Drupal\quanthub_core;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oidc\OpenidConnectSessionInterface;
use Drupal\user\UserDataInterface;

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
   * Constructs an AnonymousUserInfoTokenSubscriber object.
   */
  public function __construct(AccountInterface $current_user, CacheBackendInterface $cache, OpenidConnectSessionInterface $openid_connect_session, UserDataInterface $user_data) {
    $this->currentUser = $current_user;
    $this->cache = $cache;
    $this->openidConnectSession = $openid_connect_session;
    $this->userData = $user_data;
  }

  /**
   * Get token for anonymous from cache and authenticated user from oidc plugin.
   */
  public function getToken() {
    // For anonymous and admin we will use anonymous token.
    if ($this->currentUser->isAnonymous() || $this->currentUser->id() == 1) {
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
      $quanthub_user_id = $this->cache->get(self::ANONYMOUS_QUANHUB_USER_ID)->data;
    }
    else {
      $quanthub_user_id = $this->userData->get(
        self::MODULE_NAME,
        $this->currentUser->id(),
        self::USER_QUANTHID_ID
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
        self::USER_QUANTHID_ROLE
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
        self::USER_QUANTHID_GROUPS
      );
    }
  }

}
