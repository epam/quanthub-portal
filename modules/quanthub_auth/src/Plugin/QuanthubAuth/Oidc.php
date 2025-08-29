<?php

namespace Drupal\quanthub_auth\Plugin\QuanthubAuth;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\oidc\OpenidConnectSessionInterface;
use Drupal\quanthub_auth\QuanthubAuthPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extracts OIDC token.
 *
 * @QuanthubAuth(
 *   id = "oidc",
 *   scope = \Drupal\quanthub_auth\QuanthubAuthPluginInterface::AUTHENTICATED,
 *   label = @Translation("OpenID Connect Client token"),
 *   priority = 0
 * )
 */
class Oidc extends PluginBase implements QuanthubAuthPluginInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->has('oidc.openid_connect_session') ? $container->get('oidc.openid_connect_session') : NULL
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ?OpenidConnectSessionInterface $session,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getToken(): ?string {
    return $this->session?->getJsonWebTokens()?->getAccessToken()?->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): MarkupInterface|string {
    if (!$this->session) {
      return $this->t('<strong>Inactive</strong>: oidc module is not installed.');
    }
    return $this->t('<strong>Active</strong>');
  }

}
