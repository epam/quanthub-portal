<?php

namespace Drupal\quanthub_core;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle finished responses for the anonymous user.
 */
class AnonymousUserInfoTokenSubscriber implements EventSubscriberInterface, UserInfoInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\quanthub_core\UserInfo
   */
  protected $userInfo;

  /**
   * Constructs an AnonymousUserInfoTokenSubscriber object.
   */
  public function __construct(UserInfo $user_info) {
    $this->userInfo = $user_info;
  }

  /**
   * Check that token is existed.
   */
  public function onRequest(RequestEvent $event) {
    $this->userInfo->getToken();
  }

  /**
   * Subscribe to kernel response event.
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest', 30];
    return $events;
  }

}
