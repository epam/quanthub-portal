<?php

namespace Drupal\quanthub_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\externalauth\Event\ExternalAuthEvents;
use Drupal\externalauth\Event\ExternalAuthLoginEvent;
use Drupal\oidc\OpenidConnectSessionInterface;
use Drupal\user\UserDataInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Response subscriber to handle finished responses for the anonymous user.
 */
class UserInfoAttributesSubscriber implements EventSubscriberInterface, UserInfoInterface {

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
   * The http client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The open id connect session service.
   *
   * @var \Drupal\oidc\OpenidConnectSessionInterface
   */
  protected $openidConnectSession;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Constructs an AnonymousUserInfoTokenSubscriber object.
   */
  public function __construct(LoggerInterface $logger, ConfigFactoryInterface $configFactory, ClientInterface $http_client, OpenidConnectSessionInterface $openid_connect_session, UserDataInterface $user_data) {
    $this->logger = $logger;
    $this->configFactory = $configFactory;
    $this->httpClient = $http_client;
    $this->openidConnectSession = $openid_connect_session;
    $this->userData = $user_data;
  }

  /**
   * Get user info attributes and save to user data.
   */
  public function getUserInfoAttributes(ExternalAuthLoginEvent $event) {
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
    $user_attributes_endpoint = $oidc_plugin->get(self::USER_ATTRIBUTES_ENDPOINT);

    $token = $this->openidConnectSession->getJsonWebTokens()->getAccessToken()->getValue();

    try {
      $response = $this->httpClient->get($user_attributes_endpoint, [
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
          'Content-Type' => 'application/json',
        ],
      ]);

      $user_attributes_data = json_decode($response->getBody(), TRUE);

      $this->userData->set(
        self::MODULE_NAME,
        $event->getAccount()->id(),
        self::USER_QUANTHUB_ID,
        array_shift($user_attributes_data['userAttributes']['USER_ID'])
      );

      $this->userData->set(
        self::MODULE_NAME,
        $event->getAccount()->id(),
        self::USER_QUANTHUB_ROLE,
        $user_attributes_data['userAttributes']['USER_ROLE']
      );

      $this->userData->set(
        self::MODULE_NAME,
        $event->getAccount()->id(),
        self::USER_QUANTHUB_GROUPS,
        $user_attributes_data['userAttributes']['USER_GROUPS']
      );

      // @todo perhaps need to save USER_ORGANIZATION too.
    }
    catch (RequestException $e) {
      $this->logger->error('Failed to retrieve quanthub user id for anonymous user: @error.', [
        '@error' => $e->getMessage(),
      ]);

      throw new \RuntimeException('Failed to retrieve quanthub user id for anonymous token', 0, $e);
    }
  }

  /**
   * Subscribe to kernel response event.
   */
  public static function getSubscribedEvents() {
    $events[ExternalAuthEvents::LOGIN][] = ['getUserInfoAttributes'];
    return $events;
  }

}
