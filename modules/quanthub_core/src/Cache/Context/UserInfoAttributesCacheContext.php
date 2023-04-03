<?php

namespace Drupal\quanthub_core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Cache\Context\UserCacheContextBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\quanthub_core\UserInfoInterface;
use Drupal\user\UserDataInterface;

/**
 * Defines the User Info Attributes cache context service.
 *
 * Cache context ID: 'user_info_attributes'.
 *
 * @DCG
 * Check out the core/lib/Drupal/Core/Cache/Context directory for examples of
 * cache contexts provided by Drupal core.
 */
class UserInfoAttributesCacheContext extends UserCacheContextBase implements CacheContextInterface, UserInfoInterface {

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Constructs a new UserCacheContextBase class.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   */
  public function __construct(AccountInterface $user, UserDataInterface $user_data) {
    parent::__construct($user);

    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('User info attributes');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    if ($this->user->isAnonymous() || $this->user->id() == 1) {
      return self::QUANTHUB_ANONYMOUS_ROLE;
    }
    else {
      return $this->userData->get(
        self::MODULE_NAME,
        $this->user->id(),
        self::USER_QUANTHUB_ID
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
