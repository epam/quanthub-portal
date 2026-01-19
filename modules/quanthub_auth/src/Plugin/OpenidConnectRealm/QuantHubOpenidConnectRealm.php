<?php

namespace Drupal\quanthub_auth\Plugin\OpenidConnectRealm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\oidc\JsonWebTokens;
use Drupal\oidc\OpenidConnectRealm\OpenidConnectRealmConfigurableInterface;
use Drupal\oidc\Plugin\OpenidConnectRealm\GenericOpenidConnectRealm;
use Drupal\oidc\Token;
use GuzzleHttp\ClientInterface;
use Sop\JWX\JWK\JWK;
use Sop\JWX\JWT\JWT;
use Sop\JWX\JWT\ValidationContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Quanthub OpenID Connect realm plugin.
 *
 * @OpenidConnectRealm(
 *   id = "quanthub",
 *   name = @Translation("Quanthub B2C Realm"),
 * )
 *
 * @todo Key OIDC Scopes overrides only first [0] element of array,
 *   take care about full override.
 */
class QuantHubOpenidConnectRealm extends GenericOpenidConnectRealm implements OpenidConnectRealmConfigurableInterface {

  /**
   * The HTTP client.
   */
  protected ClientInterface $httpClient;

  /**
   * The language manager service.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
    $instance->httpClient = $container->get('http_client');
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Needs refactoring from platform side:
   *   - "aud" should be equal to "client_id"
   *   - token and refresh endpoints could be rewritten in configuration
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'aud' => NULL,
      'token_endpoint' => NULL,
      'refresh_endpoint' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Client secret is configured on token/refresh endpoints side.
    $form['general']['client_secret']['#access'] = FALSE;

    $form['general']['aud'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Audience constraint'),
      '#default_value' => $this->configuration['aud'],
      '#required' => TRUE,
    ];

    $form['general']['token_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token Endpoint'),
      '#default_value' => $this->configuration['token_endpoint'],
      '#required' => TRUE,
    ];

    $form['general']['refresh_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Refresh Token Endpoint'),
      '#default_value' => $this->configuration['refresh_endpoint'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['aud'] = $form_state->getValue('aud');
    $this->configuration['token_endpoint'] = $form_state->getValue('token_endpoint');
    $this->configuration['refresh_endpoint'] = $form_state->getValue('refresh_endpoint');

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @todo Method isn't used in current implementation.
   */
  protected function getTokenEndpoint() {
    return $this->configuration['token_endpoint'];
  }

  /**
   * {@inheritdoc}
   *
   * @todo Method isn't used in current implementation.
   */
  protected function getUserinfoEndpoint() {
    return NULL;
  }

  /**
   * Generating login url.
   *
   * {@inheritdoc}
   */
  public function getLoginUrl($state, Url $redirect_url) {
    $url = parent::getLoginUrl($state, $redirect_url);
    $query = $url->getOption('query');
    // @todo Do we still need it?
    $query['ui_locales'] = $this->languageManager->getCurrentLanguage()->getId();
    return $url->setOption('query', $query);
  }

  /**
   * Getting new user info token for authorized user.
   */
  public function getJsonWebTokensForLogin($state, $code) {
    try {
      // @todo Match OAuth2 specification.
      $response = $this->httpClient->post($this->configuration['token_endpoint'], [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'scopes' => $this->configuration['scopes'],
          'authString' => $code,
          'redirectUri' => $this->getRedirectUrl(),
        ],
      ]);

      // The User Info Data array that should contain token, expiresOn, tokenID.
      $user_info_data = json_decode($response->getBody(), TRUE);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to retrieve tokens for login from %endpoint: @error.', [
        '%endpoint' => $this->configuration['token_endpoint'],
        '@error' => $e->getMessage(),
      ]);

      throw new \RuntimeException('Failed to retrieve tokens for login', 0, $e);
    }

    return $this->getJsonWebTokensUserInfo($user_info_data);
  }

  /**
   * Refreshing token for authorized user.
   */
  public function getJsonWebTokensforRefresh(Token $refresh_token) {
    try {
      // @todo Match OAuth2 specification.
      $response = $this->httpClient->post($this->configuration['refresh_endpoint'], [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'scopes' => $this->configuration['scopes'],
          'authString' => $refresh_token->getValue(),
          'redirectUri' => $this->getRedirectUrl(),
        ],
      ]);

      // The User Info Data array that should contain token, expiresOn, tokenID.
      $user_info_data = json_decode($response->getBody(), TRUE);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to retrieve tokens for refresh from %endpoint: @error.', [
        '%endpoint' => $this->configuration['refresh_endpoint'],
        '@error' => $e->getMessage(),
      ]);

      throw new \RuntimeException('Failed to retrieve tokens for refresh', 0, $e);
    }

    $tokens = $this->getJsonWebTokensUserInfo($user_info_data, FALSE);

    return $tokens;
  }

  /**
   * Handle User Info data to claims.
   */
  protected function getJsonWebTokensUserInfo($response, $claim_data = TRUE) {
    // Ensure we have all the data we need to continue.
    if (!isset($response['token'], $response['expiresOn'], $response['tokenId'])) {
      throw new \RuntimeException('Some data is missing in the token response');
    }

    // Create the tokens object.
    $expires = strtotime($response['expiresOn']);
    $id_token = new Token($response['idToken'] ?? $response['tokenId'], $expires);
    $refresh_token = new Token($response['tokenId'], strtotime('+1 day', $expires));
    $access_token = new Token($response['token'], $expires);

    $tokens = new JsonWebTokens('user_info_token', $id_token, $access_token);
    $tokens->setRefreshToken($refresh_token);

    if ($claim_data) {
      // Parse the ID token.
      $jwt = new JWT($response['token']);

      // Get the key.
      $kid = $jwt->header()->keyID()->value();
      $key = JWK::fromArray($this->getJwk($kid));

      // Create the validation context.
      $context = ValidationContext::fromJWK($key)
        ->withIssuer($this->getIssuer())
        // @todo Use 'client_id'
        ->withAudience($this->configuration['aud']);

      // Validate and get the claims.
      $claims = $jwt->claims($context);

      foreach ($claims->all() as $claim) {
        $tokens->setClaim($claim->name(), $claim->value());
      }

      $tokens->setIdClaim($this->configuration['id_claim'])
        ->setUsernameClaim($this->configuration['username_claim'])
        ->setEmailClaim($this->configuration['email_claim'])
        ->setGivenNameClaim($this->configuration['given_name_claim'])
        ->setFamilyNameClaim($this->configuration['family_name_claim']);
    }

    return $tokens;
  }

  /**
   * Helper to get redirect URL.
   *
   * @return string
   *   The external URL string.
   */
  protected function getRedirectUrl(?Url $url = NULL) {
    $url = $url ?? Url::fromRoute('oidc.openid_connect.login_redirect');
    return $url->setAbsolute()->toString();
  }

}
