<?php

namespace Drupal\quanthub_core;

/**
 * User info token functionality interface.
 */
interface UserInfoInterface extends QuanthubCoreInterface {

  /**
   * Cache ID for anonymous user info token.
   */
  const ANONYMOUS_TOKEN_CID = self::MODULE_NAME . ':ANONYMOUS_USERINFO_TOKEN';

  /**
   * User Data key for quanthub id.
   */
  const USER_QUANTHID_ID = 'user_quanthub_id';

  /**
   * User Data key for quanthub role.
   */
  const USER_QUANTHID_ROLE = 'user_quanthub_role';

  /**
   * User Data key for quanthub groups.
   */
  const USER_QUANTHID_GROUPS = 'user_quanthub_groups';

  /**
   * Cache ID for anonymous user Quanhub User Id.
   */
  const ANONYMOUS_QUANHUB_USER_ID = self::MODULE_NAME . ':ANONYMOUS_QUANHUB_USER_ID';

  /**
   * Key for main user info token endpoint.
   */
  const TOKEN_ENDPOINT = 'user_info_endpoint';

  /**
   * Key for user info refresh endpoint.
   */
  const REFRESH_TOKEN_ENDPOINT = 'user_info_endpoint_refresh';

  /**
   * Key for anonymous user info token endpoint.
   */
  const ANONYMOUS_TOKEN_ENDPOINT = 'user_info_endpoint_anonymous';

  /**
   * Key for user info attributes endpoint.
   */
  const USER_ATTRIBUTES_ENDPOINT = 'user_info_attributes_endpoint';

  /**
   * Key for audience data.
   */
  const AUDIENCE_TOKEN_KEY = 'aud';

  /**
   * Quanthub anonymous user role.
   */
  const QUANTHUB_ANONYMOUS_ROLE = 'Quanthub.AnonymousUsers';

}
