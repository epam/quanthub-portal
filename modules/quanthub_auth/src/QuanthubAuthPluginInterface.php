<?php

namespace Drupal\quanthub_auth;

use Drupal\Component\Render\MarkupInterface;

/**
 * Interface for quanthub_auth plugins.
 */
interface QuanthubAuthPluginInterface {

  /**
   * Anonymous user scope.
   */
  const ANONYMOUS = 'anonymous';

  /**
   * Authenticated user scope.
   */
  const AUTHENTICATED = 'authenticated';

  /**
   * Returns token for authenticated user.
   */
  public function getToken(): ?string;

  /**
   * Returns status message.
   */
  public function getMessage(): MarkupInterface|string;

}
