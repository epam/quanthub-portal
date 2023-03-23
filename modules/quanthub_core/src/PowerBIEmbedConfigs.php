<?php

namespace Drupal\quanthub_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
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
   * Constructor of PowerBIEmbedConfigs.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\key\KeyRepositoryInterface $repository
   *   The key repository object.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory,
                              KeyRepositoryInterface $repository,
                              LoggerChannelFactoryInterface $loggerFactory) {
    $this->configFactory = $configFactory;
    $this->repository = $repository;
    $this->loggerFactory = $loggerFactory->get('powerbi_embed');
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
   * Get PowerBI Embed Token.
   */
  public function getPowerBiEmbedToken($token, $reportId, $datasetIds) {
    $bearerToken = "Authorization: Bearer " . $token;

    $datasets = '';
    foreach ($datasetIds as $datasetId) {
      $datasets = $datasets . "{'id': '" . $datasetId . "', 'xmlaPermissions': 'ReadOnly'},";
    }

    $curlPostToken = curl_init();
    $powerbiAPIURL = 'https://api.powerbi.com/v1.0/myorg/GenerateToken';
    $payload = "{
        'targetWorkspaces': [{'id': '" . $this->getWorkspaceID() . "'}],
        'datasets': [" . $datasets . "],
        'reports': [{'id': '" . $reportId . "'}]
    }";

    $theCurlOpts = [
      CURLOPT_URL => $powerbiAPIURL,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_HTTPHEADER => [
        $bearerToken,
        "Cache-Control: no-cache",
        "Content-Type:application/json",
      ],
    ];
    curl_setopt_array($curlPostToken, $theCurlOpts);
    $tokenResponse = curl_exec($curlPostToken);
    $tokenError = curl_error($curlPostToken);
    curl_close($curlPostToken);

    if ($tokenError) {
      $this->loggerFactory->error("getPowerBIEmbedToken: " . $tokenError);
      return NULL;
    }
    $tokenResponse = json_decode($tokenResponse, TRUE);

    if (isset($tokenResponse["error"])) {
      $this->loggerFactory->error("error: " . $tokenResponse["error"]["message"]);
      return NULL;
    }
    return $tokenResponse;
  }

  /**
   * Get PowerBI Embed Config Embed url and Embed token.
   */
  public function getPowerEmbedConfig($reportId, $extraDatasets) {
    $token = $this->getPowerBIAccessToken();
    $bearerToken = "Authorization: Bearer " . $token;

    $curlGetUrl = curl_init();
    $powerbiAPIURL = 'https://api.powerbi.com/v1.0/myorg/groups/' . $this->getWorkspaceID() . '/reports/' . $reportId;
    $theCurlOpts = [
      CURLOPT_URL => $powerbiAPIURL,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => [
        $bearerToken,
        "Cache-Control: no-cache",
      ],
    ];
    curl_setopt_array($curlGetUrl, $theCurlOpts);
    $embedResponse = curl_exec($curlGetUrl);
    $embedError = curl_error($curlGetUrl);
    curl_close($curlGetUrl);

    if ($embedError) {
      $this->loggerFactory->error("getPowerEmbedConfig: " . $embedError);
      return NULL;
    }
    $embedResponse = json_decode($embedResponse, TRUE);

    if (isset($embedResponse["error"])) {
      $this->loggerFactory->error("error: " . $embedResponse["error"]["message"]);
      return NULL;
    }

    $embedUrl = $embedResponse['embedUrl'];
    $datasetId = $embedResponse['datasetId'];

    if ($extraDatasets !== 'defaultValue') {
      $extraDatasets = preg_replace('/\s+/', ',', $extraDatasets);
      $datasetIds = preg_split("/[,]+/", $extraDatasets);
    }
    $datasetIds[] = $datasetId;

    $embedToken = $this->getPowerBIEmbedToken($token, $reportId, $datasetIds);

    return [
      "embed_url" => $embedUrl,
      "embed_token" => $embedToken,
    ];
  }

}
