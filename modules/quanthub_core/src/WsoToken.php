<?php

namespace Drupal\quanthub_core;

use Drupal\Core\Session\AccountProxy;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Psr\Log\LoggerInterface;

/**
 * WSO Token Service. Getting and saving token to cache.
 */
class WsoToken {

  /**
   * Url for request to wso2.
   *
   * @var string
   */
  protected string $route;

  /**
   * Guzzle service definition.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $httpClient;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;


  /**
   * The private tempstore service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $privateTempstore;

  /**
   * Constructor.
   */
  public function __construct(Client $http_client, LoggerInterface $logger, AccountProxy $current_user, PrivateTempStoreFactory $private_tempstore) {
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->currentUser = $current_user;
    $this->privateTempstore = $private_tempstore;

    $this->route = getenv('WSO_TOKEN_ROUTE');
  }

  /**
   * Get token from tempstore or route if expired.
   */
  public function getToken(): string {
    if (!$access_token = $this->privateTempstore->get('wso')->get('token')) {
      $access_token = $this->getOriginalToken();
      $this->privateTempstore->get('wso')->set('token', $access_token);
    }

    return $access_token;

  }

  /**
   * Get Original token from route.
   */
  public function getOriginalToken() {
    try {
      if ((int) getenv('IS_LOCAL_ENV')) {
        // On local env we need to use api with client id, secret etc.
        $response = $this->httpClient->post($this->route, [
          'form_params' => [
            'client_id' => getenv('WSO_TOKEN_CLIENT_ID'),
            'client_secret' => getenv('WSO_TOKEN_CLIENT_SECRET'),
            'grant_type' => getenv('WSO_TOKEN_GRANT_TYPE'),
            'scope' => getenv('WSO_TOKEN_SCOPE'),
          ],
        ]);
      }
      else {
        // In a cluster we are just using request to MSI.
        $response = $this->httpClient->get($this->route, ['headers' => ['Metadata' => 'true']]);
      }
      $response_data = json_decode($response->getBody() ?? '');
      return $response_data->access_token;
    }
    catch (ConnectException $e) {
      $this->logger->notice('Problem with getting WSO Token list');
    }
  }

}
