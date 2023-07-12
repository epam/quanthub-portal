<?php

namespace Drupal\quanthub_sdmx_sync;

use Drupal\Core\Http\ClientFactory;
use Drupal\quanthub_core\UserInfo;
use Psr\Log\LoggerInterface;

/**
 * SDMX client service.
 */
class QuanthubSdmxClient {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The user info service.
   *
   * @var \Drupal\quanthub_core\UserInfo
   */
  protected UserInfo $userInfo;

  /**
   * The HTTP client to fetch the API data from SDMX.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected ClientFactory $httpClientFactory;

  /**
   * The list of headers.
   *
   * @var array
   */
  private array $headers = [
    'Accept' => 'application/xml',
    'Accept-Encoding' => 'gzip',
  ];

  /**
   * Construct SDMX client.
   *
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   *   A Guzzle client object.
   * @param \Drupal\quanthub_core\UserInfo $user_info
   *   The user info service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(ClientFactory $http_client_factory, UserInfo $user_info, LoggerInterface $logger) {
    $this->httpClientFactory = $http_client_factory;
    $this->userInfo = $user_info;
    $this->logger = $logger;

    $this->headers['authorization'] = 'Bearer ' . $this->userInfo->getToken();
  }

  /**
   * The get request to SDMX api.
   *
   * @param string $urn
   *   The dataset urn.
   *
   * @return array
   *   The response body array decoded json.
   */
  public function getDasetStructure(string $urn) {
    $baseUri = getenv('SDMX_API_URL') . '/workspaces/' . getenv('SDMX_WORKSPACE_ID') . '/registry/sdmx-plus/structure/dataflow/';

    $guzzleClient = $this->httpClientFactory->fromOptions([
      'base_uri' => $baseUri,
      'headers' => $this->headers,
      'query' => [
        'detail' => 'allcompletestubs',
        'references' => 'none',
      ],
    ]);

    $urn_for_url = $this->transformUrn($urn);

    try {
      return json_decode($guzzleClient->get($urn_for_url)->getBody(), TRUE);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to retrieve tokens for anonymous user: @error.', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Get dataset filtered data by method GET.
   *
   * @param string $urn
   *   The dataset urn.
   * @param string $filters
   *   The filters string divided by dot.
   *
   * @return mixed|void
   *   The decoded response from SDMX.
   */
  public function getDatasetFilteredData(string $urn, string $filters) {
    $baseUri = getenv('SDMX_API_URL') . '/workspaces/' . getenv('SDMX_WORKSPACE_ID') . '/registry/sdmx/3.0/data/dataflow/';

    $guzzleClient = $this->httpClientFactory->fromOptions([
      'base_uri' => $baseUri,
      'headers' => $this->headers,
    ]);

    $urn_for_url = $this->transformUrn($urn);

    try {
      return json_decode(
        $guzzleClient->get($urn_for_url . '/' . $filters)->getBody(),
        TRUE
      );
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to retrieve tokens for anonymous user: @error.', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Transform dataset urn for using in api request.
   *
   * @param string $dataset_urn
   *   The dataset urn string.
   *
   * @return string
   *   Transformed dataset urn string.
   */
  public function transformUrn(string $dataset_urn): string {
    // Change divider between agency and dataset id.
    $dataset_urn_url = str_replace(':', '/', $dataset_urn);
    // Transform versioning of dataset logic.
    if (preg_match("/\([0-9.]/", $dataset_urn_url)) {
      $dataset_urn_url = str_replace('(', '/', $dataset_urn_url);
      $dataset_urn_url = str_replace(')', '', $dataset_urn_url);
    }
    else {
      // We need latest dataset version, if version isn't specified.
      $dataset_urn_url = str_replace('(*)', '/latest', $dataset_urn_url);
    }

    return $dataset_urn_url;
  }

}
