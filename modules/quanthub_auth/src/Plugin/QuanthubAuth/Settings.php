<?php

namespace Drupal\quanthub_auth\Plugin\QuanthubAuth;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Site\Settings as DrupalSettings;
use Drupal\quanthub_auth\QuanthubAuthPluginInterface;

/**
 * Provides static token from Drupal settings.
 *
 * @QuanthubAuth(
 *   id = "settings",
 *   scope = \Drupal\quanthub_auth\QuanthubAuthPluginInterface::ANONYMOUS,
 *   label = @Translation("Static token from Drupal settings"),
 *   priority = -70
 * )
 */
class Settings extends PluginBase implements QuanthubAuthPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getToken(): ?string {
    $settings = DrupalSettings::get('quanthub_auth');
    return $settings['anonymous_token'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): MarkupInterface|string {
    if (!$this->getToken()) {
      return $this->t("<strong>Inactive</strong>: <em>\$settings['quanthub_auth']['anonymous_token']</em> value is empty.");
    }
    return $this->t('<strong>Active</strong>');
  }

}
