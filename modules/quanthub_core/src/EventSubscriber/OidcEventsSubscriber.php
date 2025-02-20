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
   * Extra roles mapping.
   */
  const DEFAULT_ROLES = [
    'Quanthub.PortalContentEditor' => 'content_editor',
  ];

  /**
   * The OpenID Connect session service.
   *
   * @var \Drupal\oidc\OpenidConnectSessionInterface
   */
  protected $session;

  /**
   * The roles cache.
   *
   * @var array|null
   */
  private static $roles;

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
    $roles_map = self::getRolesMap();

    // The provider must match the realm and provide the claim.
    if (!$plugin_id || $provider !== $event->getProvider() || $roles_claim === NULL) {
      return;
    }

    $account = $event->getAccount();
    $user_roles = $account->getRoles(TRUE);
    // Keep roles we don't track with SSO provider.
    $oidc_roles = array_diff($user_roles, array_filter($roles_map));

    if (is_array($roles_claim)) {
      foreach ($roles_claim as $role) {
        if (empty($roles_map[$role])) {
          continue;
        }
        $oidc_roles[] = $roles_map[$role];
      }
    }

    // Only generic realms support this.
    $plugin = $this->session->getRealmPlugin();
    if ($plugin instanceof GenericOpenidConnectRealm && $plugin->getDefaultRoleId()) {
      $oidc_roles[] = $plugin->getDefaultRoleId();
    }

    // Check do we need an update.
    if (array_diff($oidc_roles, $user_roles) || array_diff($user_roles, $oidc_roles)) {
      $account->set('roles', array_unique($oidc_roles))->save();
    }
  }

  /**
   * Helper to get roles.
   */
  protected static function getRolesMap() {
    if (!isset(self::$roles)) {
      $roles = \Drupal::moduleHandler()->invokeAll('quanthub_core_roles');
      self::$roles = $roles + self::DEFAULT_ROLES;
    }
    return self::$roles;
  }

}
