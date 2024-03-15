<?php

namespace Drupal\quanthub_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;
use Jumbojett\OpenIDConnectClient;

/**
 * Return Power BI embed configs.
 */
class PowerBIEmbedConfigs {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Key repository object.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $repository;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The http client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructor of PowerBIEmbedConfigs.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\key\KeyRepositoryInterface $repository
   *   The key repository object.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The http client.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    KeyRepositoryInterface $repository,
    LoggerChannelFactoryInterface $loggerFactory,
    ClientInterface $httpClient
  ) {
    $this->configFactory = $configFactory;
    $this->repository = $repository;
    $this->loggerFactory = $loggerFactory->get('powerbi_embed');
    $this->httpClient = $httpClient;
  }

  /**
   * Return PowerBI configuration settings.
   */
  private function getConfig() {
    return $this->configFactory->get('powerbi_embed.settings');
  }

  /**
   * Return PowerBI configured Azure Client ID.
   */
  public function getClientId() {
    return $this->getConfig()->get('client_id');
  }

  /**
   * Return PowerBI configured Workspace ID.
   */
  public function getWorkspaceId() {
    return $this->getConfig()->get('workspace_id');
  }

  /**
   * Return PowerBI configured user name.
   */
  public function getUsername() {
    return $this->getConfig()->get('username');
  }

  /**
   * Return PowerBI configured password.
   */
  public function getPassword() {
    $config_password = $this->getConfig()->get('password');
    return $this->repository
      ->getKey($config_password)
      ->getKeyValue();
  }

  /**
   * Get PowerBI Access Token.
   */
  public function getPowerBiAccessToken() {
    $oidc = new OpenIDConnectClient(
      'https://login.microsoftonline.com/' . $this->getClientID(),
      $this->getUsername(),
      $this->getPassword()
    );

    $oidc->providerConfigParam(['token_endpoint' => 'https://login.microsoftonline.com/' . $this->getClientID() . '/oauth2/v2.0/token']);
    $oidc->addScope('https://analysis.windows.net/powerbi/api/.default');
    $oidc_response = $oidc->requestClientCredentialsToken();
    return $oidc_response->access_token;
  }

  /**
   * Get PowerBI dataset details.
   */
  public function getPowerBiDataset($token, $datasetId) {
    $powerbiAPIURL = 'https://api.powerbi.com/v1.0/myorg/groups/' . $this->getWorkspaceID() . '/datasets/' . $datasetId;

    try {
      $request = $this->httpClient->request(
        'GET',
        $powerbiAPIURL,
        [
          'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Cache-Control' => 'no-cache',
          ],
          'connect_timeout' => 30,
          'allow_redirects' => [
            'max' => 10,
          ],
        ]
      );
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('getPowerBiDataset: ' . $e->getMessage());
      return NULL;
    }

    $datasetResponse = json_decode($request->getBody(), TRUE);
    return $datasetResponse;
  }

  /**
   * Get PowerBI Embed Token.
   */
  public function getPowerBiEmbedToken($token, $reportId, $datasetIds) {
    $powerbiAPIURL = 'https://api.powerbi.com/v1.0/myorg/GenerateToken';
    $powerbiUser = getenv('POWERBI_PUBLIC_USER');
    $powerbiRole = getenv('POWERBI_PUBLIC_ROLE');

    $entitledDatasets = [];
    foreach ($datasetIds as $datasetId) {
      $response = $this->getPowerBiDataset($token, $datasetId);
      if ($response['isEffectiveIdentityRequired'] && $response['isEffectiveIdentityRolesRequired']) {
        $entitledDatasets[] = $datasetId;
      }
    }

    $datasets = [];
    foreach ($datasetIds as $datasetId) {
      $datasets[] = [
        'id' => $datasetId,
        'xmlaPermissions' => 'ReadOnly',
      ];
    }

    $payload = [
      'accessLevel' => 'View',
      'datasets' => $datasets,
      'reports' => [['id' => $reportId]],
      'targetWorkspaces' => [['id' => $this->getWorkspaceID()]],
    ];

    if (count($entitledDatasets) > 0) {
      $payload['identities'] = [[
        'username' => $powerbiUser, 
        'roles' => [$powerbiRole],
        'datasets' => $entitledDatasets
      ]];
    }

    $payload_json = json_encode($payload);

    try {
      $request = $this->httpClient->request(
        'POST',
        $powerbiAPIURL,
        [
          'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'application/json',
          ],
          'connect_timeout' => 30,
          'allow_redirects' => [
            'max' => 10,
          ],
          'body' => $payload_json,
        ]
      );
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('getPowerBIEmbedToken: ' . $e->getMessage());
      return NULL;
    }

    $tokenResponse = json_decode($request->getBody(), TRUE);

    if (isset($tokenResponse['error'])) {
      if (isset($tokenResponse['error']['message'])) {
        $this->loggerFactory->error('error: ' . $tokenResponse['error']['message']);
      }
      else {
        $this->loggerFactory->error('error: ' . $tokenResponse['error']);
      }
      return NULL;
    }
    return $tokenResponse;
  }

  /**
   * Get PowerBI Embed Config Embed url and Embed token.
   */
  public function getPowerEmbedConfig($reportId, $extraDatasets = '') {
    $token = $this->getPowerBIAccessToken();
    $powerbiAPIURL = 'https://api.powerbi.com/v1.0/myorg/groups/' . $this->getWorkspaceID() . '/reports/' . $reportId;

    try {
      $request = $this->httpClient->request(
        'GET',
        $powerbiAPIURL,
        [
          'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Cache-Control' => 'no-cache',
          ],
          'connect_timeout' => 30,
          'allow_redirects' => [
            'max' => 10,
          ],
        ]
      );
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('getPowerEmbedConfig: ' . $e->getMessage());
      return NULL;
    }

    $embedResponse = json_decode($request->getBody(), TRUE);

    if (isset($embedResponse['error'])) {
      $this->loggerFactory->error('error: ' . $embedResponse['error']['message']);
      return NULL;
    }

    $embedUrl = $embedResponse['embedUrl'];
    $datasetId = $embedResponse['datasetId'];

    if (!empty(trim($extraDatasets))) {
      $extraDatasets = preg_replace('/\s+/', ',', $extraDatasets);
      $datasetIds = preg_split('/[,]+/', $extraDatasets);
    }
    $datasetIds[] = $datasetId;

    $embedToken = $this->getPowerBIEmbedToken($token, $reportId, $datasetIds);

    return [
      'embed_url' => $embedUrl,
      'embed_token' => $embedToken,
    ];
  }

}
