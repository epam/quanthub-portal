<?php

namespace Drupal\quanthub_core;

/**
 * Handles Azure Workload Identity token acquisition and management.
 *
 * Provides functionality to retrieve and cache access tokens using Azure's
 * Workload Identity Federation with automatic token refresh capabilities.
 */
class AzureWorkloadIdentityTokenProvider {
  // Default cache filename.
  private const DEFAULT_TOKEN_FILE = "default_workload_identity_token_file";
  // Default Azure scope.
  private const DEFAULT_SCOPE = 'https://ossrdbms-aad.database.windows.net/.default';
  // Buffer time for token expiration (10 minutes)
  private const EXPIRES_BEFORE_SEC = 600;
  // Default Azure authority host.
  private const DEFAULT_AUTHORITY_HOST = 'https://login.microsoftonline.com/';

  /**
   * Azure Client ID from environment or constructor.
   *
   * @var string */
  private string $clientId;
  /**
   * Azure Tenant ID from environment or constructor.
   *
   * @var string */
  private string $tenantId;
  /**
   * Path to federated token file from environment or constructor.
   *
   * @var string */
  private string $federatedTokenFile;
  /**
   * Azure authority host URL.
   *
   * @var string */
  private string $authorityHost;

  /**
   * Initialize token provider with configuration.
   *
   * @param string|null $clientId
   *   Azure Client ID (default: AZURE_CLIENT_ID env)
   * @param string|null $tenantId
   *   Azure Tenant ID (default: AZURE_TENANT_ID env)
   * @param string|null $federatedTokenFile
   *   Path to federated token file (default: AZURE_FEDERATED_TOKEN_FILE env)
   * @param string|null $authorityHost
   *   Azure authority host URL (default: AZURE_AUTHORITY_HOST env
   *   or DEFAULT_AUTHORITY_HOST)
   */
  public function __construct(
    ?string $clientId = NULL,
    ?string $tenantId = NULL,
    ?string $federatedTokenFile = NULL,
    ?string $authorityHost = NULL,
  ) {
    $this->clientId = $clientId ?? getenv('AZURE_CLIENT_ID') ?: '';
    $this->tenantId = $tenantId ?? getenv('AZURE_TENANT_ID') ?: '';
    $this->federatedTokenFile = $federatedTokenFile ?? getenv('AZURE_FEDERATED_TOKEN_FILE') ?: '';
    $this->authorityHost = $authorityHost ?? getenv('AZURE_AUTHORITY_HOST') ?: self::DEFAULT_AUTHORITY_HOST;

    $this->validateConfig();
  }

  /**
   * Retrieve access token, using cached version if available and valid.
   *
   * @param string $scope
   *   Azure scope for the token (default: DEFAULT_SCOPE)
   * @param string|null $cachedTokenFileName
   *   Custom cache filename (default: DEFAULT_TOKEN_FILE)
   *
   * @return string
   *   Valid access token
   *
   * @throws RuntimeException
   *   On configuration or token retrieval errors.
   */
  public function getToken(string $scope = self::DEFAULT_SCOPE, ?string $cachedTokenFileName = NULL): string {
    $cacheFile = $cachedTokenFileName ?? self::DEFAULT_TOKEN_FILE;
    return $this->getCachedToken($cacheFile) ?? $this->fetchNewToken($scope, $cacheFile);
  }

  /**
   * Validate required configuration parameters.
   *
   * @throws RuntimeException
   *   If any required configuration is missing.
   */
  private function validateConfig(): void {
    if (empty($this->clientId) || empty($this->tenantId) || empty($this->federatedTokenFile)) {
      throw new RuntimeException(
        'AZURE_CLIENT_ID, AZURE_TENANT_ID, and AZURE_FEDERATED_TOKEN_FILE must be set for Workload Identity.'
      );
    }
  }

  /**
   * Retrieve cached token if valid.
   *
   * @param string $cachedTokenFileName
   *   Cache filename to check.
   *
   * @return string|null
   *   Valid access token or null if not available/expired.
   */
  private function getCachedToken(string $cachedTokenFileName): ?string {
    $cachedTokenFile = $this->getCachedTokenFullPath($cachedTokenFileName);

    if (!file_exists($cachedTokenFile)) {
      return NULL;
    }

    $content = file_get_contents($cachedTokenFile);
    if ($content === FALSE) {
      return NULL;
    }

    $tokenData = json_decode($content, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($tokenData['expires_in'])) {
      return NULL;
    }

    if ((intval($tokenData['expires_in']) - self::EXPIRES_BEFORE_SEC) > time()) {
      return $tokenData['access_token'] ?? NULL;
    }

    return NULL;
  }

  /**
   * Fetch and cache new access token from Azure.
   *
   * @param string $scope
   *   Azure scope for the token.
   * @param string $cachedTokenFileName
   *   Cache filename to use.
   *
   * @return string
   *   New access token
   *
   * @throws RuntimeException
   *   On token retrieval or cache failures.
   */
  private function fetchNewToken(string $scope, string $cachedTokenFileName): string {
    $jwt = file_get_contents($this->federatedTokenFile);
    if ($jwt === FALSE) {
      throw new RuntimeException('Failed to read federated token file.');
    }

    $tokenData = $this->makeTokenRequest([
      'client_id' => $this->clientId,
      'jwt' => $jwt,
      'scope' => $scope,
    ]);

    $cachedTokenFile = $this->getCachedTokenFullPath($cachedTokenFileName);
    $tokenData['expires_in'] = time() + intval($tokenData['expires_in']);
    file_put_contents($cachedTokenFile, json_encode($tokenData));

    if (!isset($tokenData['access_token'])) {
      throw new RuntimeException('Workload Identity token exchange failed: Invalid response.');
    }

    return $tokenData['access_token'];
  }

  /**
   * Execute token request to Azure endpoint.
   *
   * @param array $credentials
   *   Request parameters.
   *
   * @return array
   *   Decoded token response
   *
   * @throws RuntimeException
   *   On request failures or invalid responses.
   */
  private function makeTokenRequest(array $credentials): array {
    $ch = curl_init();
    $tokenUrl = $this->authorityHost . $this->tenantId . "/oauth2/v2.0/token";

    curl_setopt_array($ch, [
      CURLOPT_URL => $tokenUrl,
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $credentials['client_id'],
        'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
        'client_assertion' => $credentials['jwt'],
        'scope' => $credentials['scope'],
      ]),
      CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_SSL_VERIFYPEER => TRUE,
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
      $error = curl_error($ch);
      curl_close($ch);
      throw new RuntimeException("Azure AD token exchange failed: $error");
    }
    curl_close($ch);

    $tokenData = json_decode($response, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new RuntimeException('Invalid JSON response from token endpoint');
    }

    return $tokenData;
  }

  /**
   * Generate full path for cached token file.
   *
   * @param string $fileName
   *   Cache filename.
   *
   * @return string
   *   Full filesystem path
   */
  private function getCachedTokenFullPath(string $fileName): string {
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;
  }

}
