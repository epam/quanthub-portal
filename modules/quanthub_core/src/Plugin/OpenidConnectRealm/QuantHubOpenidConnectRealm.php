<?php

namespace Drupal\quanthub_core\Plugin\OpenidConnectRealm;

use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Url;
use Drupal\quanthub_core\UserInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oidc\JsonWebTokens;
use Drupal\oidc\OpenidConnectRealm\OpenidConnectRealmConfigurableInterface;
use Drupal\oidc\Plugin\OpenidConnectRealm\GenericOpenidConnectRealm;
use Drupal\oidc\Token;
use Sop\JWX\JWK\JWK;
use Sop\JWX\JWT\JWT;
use Sop\JWX\JWT\ValidationContext;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the generic OpenID Connect realm plugin.
 *
 * @OpenidConnectRealm(
 *   id = "quanthub_b2c_realm",
 *   name = @Translation("Quanthub B2C Realm"),
 *   deriver = "Drupal\oidc\Plugin\Derivative\GenericOpenidConnectRealmDeriver"
 * )
 */
class QuantHubOpenidConnectRealm extends GenericOpenidConnectRealm implements OpenidConnectRealmConfigurableInterface, UserInfoInterface {

  /**
   * Generating login url.
   *
   * Mostly the same as parent method but
   * added ui_locales to generating login link.
   *
   * {@inheritdoc}
   */
  public function getLoginUrl($state, Url $redirect_url) {
    return Url::fromUri($this->getAuthorizationEndpoint(), [
      'query' => [
        'response_type' => 'code',
        'scope' => $this->getScopeParameter(),
        'client_id' => $this->getClientId(),
        'state' => $state,
        'redirect_uri' => $redirect_url->setAbsolute()->toString(TRUE)->getGeneratedUrl(),
        'ui_locales' => $this->languageManager->getCurrentLanguage()->getId(),
      ],
    ]);
  }

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

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
    $instance->setLanguageManager($container->get('language_manager'));
    return $instance;
  }

  /**
   * Sets language manager.
   */
  public function setLanguageManager(LanguageManager $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array_merge(parent::defaultConfiguration(),
      [
        self::AUDIENCE_TOKEN_KEY => NULL,
        self::TOKEN_ENDPOINT => NULL,
        self::REFRESH_TOKEN_ENDPOINT => NULL,
        self::ANONYMOUS_TOKEN_ENDPOINT => NULL,
        self::USER_ATTRIBUTES_ENDPOINT => NULL,
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form[self::AUDIENCE_TOKEN_KEY] = [
      '#type' => 'textfield',
      '#title' => $this->t('Audience'),
      '#default_value' => $this->configuration[self::AUDIENCE_TOKEN_KEY],
      '#required' => TRUE,
    ];

    $form[self::TOKEN_ENDPOINT] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Info Endpoint'),
      '#default_value' => $this->configuration[self::TOKEN_ENDPOINT],
      '#required' => TRUE,
    ];

    $form[self::REFRESH_TOKEN_ENDPOINT] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Info Endpoint Refresh Token'),
      '#default_value' => $this->configuration[self::REFRESH_TOKEN_ENDPOINT],
      '#required' => TRUE,
    ];

    $form[self::ANONYMOUS_TOKEN_ENDPOINT] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Info Endpoint Anonymous Token'),
      '#default_value' => $this->configuration[self::ANONYMOUS_TOKEN_ENDPOINT],
      '#required' => TRUE,
    ];

    $form[self::USER_ATTRIBUTES_ENDPOINT] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Info Endpoint Anonymous Token'),
      '#default_value' => $this->configuration[self::USER_ATTRIBUTES_ENDPOINT],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration[self::AUDIENCE_TOKEN_KEY] = $form_state->getValue(self::AUDIENCE_TOKEN_KEY);
    $this->configuration[self::TOKEN_ENDPOINT] = $form_state->getValue(self::TOKEN_ENDPOINT);
    $this->configuration[self::REFRESH_TOKEN_ENDPOINT] = $form_state->getValue(self::REFRESH_TOKEN_ENDPOINT);
    $this->configuration[self::ANONYMOUS_TOKEN_ENDPOINT] = $form_state->getValue(self::ANONYMOUS_TOKEN_ENDPOINT);
    $this->configuration[self::USER_ATTRIBUTES_ENDPOINT] = $form_state->getValue(self::USER_ATTRIBUTES_ENDPOINT);

    parent::submitConfigurationForm($form, $form_state);
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
    $id_token = new Token($response['tokenId'], $expires);
    $access_token = new Token($response['token'], strtotime('+1 day', $expires));

    $tokens = new JsonWebTokens('user_info_token', $id_token, $access_token);

    if (isset($response['tokenId'], $response['expiresOn'])) {
      $tokens->setRefreshToken($id_token);
    }

    if ($claim_data) {
      // Parse the ID token.
      $jwt = new JWT($response['token']);

      // Get the key.
      $kid = $jwt->header()->keyID()->value();
      $key = JWK::fromArray($this->getJwk($kid));

      // Create the validation context.
      $context = ValidationContext::fromJWK($key)
        ->withIssuer($this->getIssuer())
        ->withAudience($this->configuration[self::AUDIENCE_TOKEN_KEY]);

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
   * Getting new user info token for authorized user.
   */
  public function getJsonWebTokensForLogin($state, $code) {
    $guzzle_client = new Client();

    try {
      $response = $guzzle_client->post($this->configuration[self::TOKEN_ENDPOINT], [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'scopes' => $this->configuration['scopes'],
          'authString' => $code,
        ],
      ]);

      // The User Info Data array that should contain token, expiresOn, tokenID.
      $user_info_data = json_decode($response->getBody(), TRUE);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to retrieve tokens for login from %endpoint: @error.', [
        '%endpoint' => $this->configuration[self::TOKEN_ENDPOINT],
        '@error' => $e->getMessage(),
      ]);

      throw new \RuntimeException('Failed to retrieve the user info', 0, $e);
    }

    return $this->getJsonWebTokensUserInfo($user_info_data);
  }

  /**
   * Refreshing token for authorized user.
   */
  public function getJsonWebTokensforRefresh(Token $refresh_token) {
    $guzzle_client = new Client();

    try {
      $response = $guzzle_client->post($this->configuration[self::REFRESH_TOKEN_ENDPOINT], [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'scopes' => $this->configuration['scopes'],
          'authString' => $refresh_token,
        ],
      ]);

      // The User Info Data array that should contain token, expiresOn, tokenID.
      $user_info_data = json_decode($response->getBody(), TRUE);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to retrieve tokens for login from %endpoint: @error.', [
        '%endpoint' => $this->configuration[self::REFRESH_TOKEN_ENDPOINT],
        '@error' => $e->getMessage(),
      ]);

      throw new \RuntimeException('Failed to refresh the user info', 0, $e);
    }

    $tokens = $this->getJsonWebTokensUserInfo($user_info_data, FALSE);

    return $tokens;
  }

}
