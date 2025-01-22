<?php

namespace Drupal\quanthub_core\EventSubscriber;

use Drupal\externalauth\Event\ExternalAuthEvents;
use Drupal\externalauth\Event\ExternalAuthLoginEvent;
use Drupal\oidc\OpenidConnectSessionInterface;
use Drupal\oidc\Plugin\OpenidConnectRealm\GenericOpenidConnectRealm;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to assign user roles.
 */
class OidcEventsSubscriber implements EventSubscriberInterface {

  /**
   * Roles mapping.
   *
   * @todo make configurable.
   */
  const ROLES = [
    'DataPlatformBasic' => '',
    'DataPlatformEnhanced' => '',
    'DataPlatformMedia' => 'media',
    'PortalContentEditor' => 'content_editor',
    'AiAssistant' => 'ai',
  ];

  /**
   * The OpenID Connect session service.
   *
   * @var \Drupal\oidc\OpenidConnectSessionInterface
   */
  protected $session;

  /**
   * {@inheritdoc}
   */
  public function __construct(OpenidConnectSessionInterface $session) {
    $this->session = $session;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ExternalAuthEvents::LOGIN][] = 'onLogin';

    return $events;
  }

  /**
   * Updates the synced user roles on login.
   *
   * @param \Drupal\externalauth\Event\ExternalAuthLoginEvent $event
   *   The login event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onLogin(ExternalAuthLoginEvent $event) {
    $plugin_id = $this->session->getRealmPluginId();
    $provider = 'oidc:' . $this->session->getRealmPluginId();
    $roles_claim = $this->session->getJsonWebTokens()->getClaim('roles');

    // The provider must match the realm and provide the claim.
    if (!$plugin_id || $provider !== $event->getProvider() || $roles_claim === NULL) {
      return;
    }

    $roles = [];
    if (is_array($roles_claim)) {
      foreach ($roles_claim as $role) {
        if (empty(self::ROLES[$role])) {
          continue;
        }
        $roles[] = self::ROLES[$role];
      }
    }

    // Only generic realms support this.
    $plugin = $this->session->getRealmPlugin();
    if ($plugin instanceof GenericOpenidConnectRealm && $plugin->getDefaultRoleId()) {
      $roles[] = $plugin->getDefaultRoleId();
    }

    $event->getAccount()
      ->set('roles', array_unique($roles))
      ->save();
  }

}
