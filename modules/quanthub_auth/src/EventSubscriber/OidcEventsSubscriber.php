<?php

namespace Drupal\quanthub_auth\EventSubscriber;

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
   * {@inheritdoc}
   */
  public function __construct(
    protected ?OpenidConnectSessionInterface $session,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    if (class_exists(ExternalAuthEvents::class)) {
      $events[ExternalAuthEvents::LOGIN][] = 'onLogin';
    }

    return $events ?? [];
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
    if (!$this->session) {
      return;
    }

    $plugin_id = $this->session->getRealmPluginId();
    $provider = 'oidc:' . $this->session->getRealmPluginId();
    // The provider must match the realm.
    if (!$plugin_id || $provider !== $event->getProvider()) {
      return;
    }

    $plugin = $this->session->getRealmPlugin();
    $configuration = $plugin->getConfiguration();
    $claim_id = $configuration['third_party_settings']['quanthub_auth']['roles_claim'] ?? NULL;
    // The claim must exist both in the token and configuration.
    if (!$claim_id || ($claim = $this->session->getJsonWebTokens()->getClaim($claim_id)) === NULL) {
      return;
    }

    $account = $event->getAccount();
    $user_roles = $account->getRoles(TRUE);
    $roles_map = $configuration['third_party_settings']['quanthub_auth']['roles_mapping'] ?? [];
    // Keep roles we don't track with SSO provider.
    $oidc_roles = array_diff($user_roles, array_keys($roles_map));

    foreach ($roles_map as $role_id => $claim_value) {
      if (in_array($claim_value, (array) $claim)) {
        $oidc_roles[] = $role_id;
      }
    }

    // Only generic realms support this.
    if ($plugin instanceof GenericOpenidConnectRealm && $plugin->getDefaultRoleId()) {
      $oidc_roles[] = $plugin->getDefaultRoleId();
    }

    // Check do we need an update.
    if (array_diff($oidc_roles, $user_roles) || array_diff($user_roles, $oidc_roles)) {
      $account->set('roles', array_unique($oidc_roles))->save();
    }
  }

}
