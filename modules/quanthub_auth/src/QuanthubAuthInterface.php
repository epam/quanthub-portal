<?php

namespace Drupal\quanthub_auth;

/**
 * Interface for quanthub.auth service.
 */
interface QuanthubAuthInterface {

  /**
   * Returns authenticated user token.
   */
  public function getUserToken(): ?string;

  /**
   * Returns anonymous user token.
   */
  public function getAnonymousToken(): ?string;

}
