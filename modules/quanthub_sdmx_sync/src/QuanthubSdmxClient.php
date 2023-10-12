<?php

namespace Drupal\quanthub_sdmx_sync;

use Drupal\Core\Http\ClientFactory;
use Drupal\quanthub_core\UserInfo;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;

/**
 * SDMX client service.
 */
class QuanthubSdmxClient {

  /**
   * The dataset structure dimension component id.
   */
  const STRUCTURE_DIMENSION_ID = 'INDICATOR';

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
    'Accept' => 'application/json',
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
   * @param bool $full_structure
   *   The option of getting structure.
   *
   * @return array
   *   The response body array decoded json.
   */
  public function getDasetStructure(string $urn, $full_structure = FALSE) {
    $baseUri = getenv('SDMX_API_URL') . '/workspaces/' . getenv('SDMX_WORKSPACE_ID') . '/registry/sdmx-plus/structure/dataflow/';

    $guzzleClient = $this->httpClientFactory->fromOptions([
      'base_uri' => $baseUri,
      'headers' => $this->headers,
      'query' => [
        'detail' => $full_structure ? 'full' : 'allcompletestubs',
        'references' => $full_structure ? 'all' : 'none',
      ],
    ]);

    $urn_for_url = $this->transformUrn($urn);

    try {
      return json_decode($guzzleClient->get($urn_for_url)->getBody(), TRUE);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to retrieve dataset structure: @error.', [
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
      $this->logger->error('Failed to retrieve filtered dataset data: @error.', [
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

  /**
   * Get dimensions list from dataset structure.
   *
   * @param string $urn
   *   The dataset urn string.
   */
  public function getDimensions(string $urn) {
    $dataset_structure = $this->getDasetStructure($urn, TRUE);

    $dimensions = [];
    if (!empty($dataset_structure['data']['dataStructures'][0]['dataStructureComponents']['dimensionList']['dimensions'])) {
      $dimensions = array_column($dataset_structure['data']['dataStructures'][0]['dataStructureComponents']['dimensionList']['dimensions'], 'id');
    }

    return $dimensions;
  }

  /**
   * Get availability for dataset.
   *
   * @param string $urn
   *   The dataset urn string.
   */
  public function datasetAvaiability(string $urn) {
    $baseUri = getenv('SDMX_API_URL') . '/workspaces/' . getenv('SDMX_WORKSPACE_ID') . '/registry/sdmx-plus/availability/dataflow/';

    $guzzleClient = $this->httpClientFactory->fromOptions([
      'base_uri' => $baseUri,
      'headers' => $this->headers,
    ]);

    $urn_for_url = $this->transformUrn($urn);

    $empty_body = [
      'endPeriod' => '9999A',
      'filters' => [],
      'mode' => 'available',
      'references' => 'none',
      'startPeriod' => '0001A',
    ];

    try {
      return json_decode($guzzleClient->post(
        $urn_for_url,
        [RequestOptions::JSON => $empty_body]
      )->getBody(), TRUE);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to retrieve dataset structure: @error.', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Get indicators for dataset.
   *
   * @param string $urn
   *   The dataset urn.
   * @param string $dimension_id
   *   The indicator dimension id.
   * @param array $selected_indicators
   *   The parameter for getting only selected indicators.
   *
   * @return array
   *   The dataset indicators list.
   */
  public function datasetIndicators(string $urn, string $dimension_id = '', array $selected_indicators = []) {
    $dimension_id = $dimension_id ?: self::STRUCTURE_DIMENSION_ID;
    $indicators = [];

    $avaiability_data = $this->datasetAvaiability($urn);
    if (empty($selected_indicators) && !empty($avaiability_data['data']['dataConstraints'][0]['cubeRegions'][0]['memberSelection'])) {
      $member_selection = $avaiability_data['data']['dataConstraints'][0]['cubeRegions'][0]['memberSelection'];
      foreach ($member_selection as $value) {
        // Should be hardcoded, the same as in structure query dimension id.
        if ($value['componentId'] == $dimension_id) {
          $indicators = array_column($value['selectionValues'], 'memberValue');
          break;
        }
      }
    }

    $dataset_structure = $this->getDasetStructure($urn, TRUE);
    if ($dataset_structure['data']['glossaries']) {
      $dataset_glossaries = $dataset_structure['data']['glossaries'];

      if (!empty($dataset_structure['data']['dataStructures'][0]['dataStructureComponents']['dimensionList']['dimensions'])) {
        $dimensions = $dataset_structure['data']['dataStructures'][0]['dataStructureComponents']['dimensionList']['dimensions'];
        foreach ($dimensions as $dimension) {

          // Should be hardcoded, the same as
          // in availability query dimension componentId.
          if ($dimension['id'] == $dimension_id) {
            $full_indicator_concept_identity = $dimension['conceptIdentity'];
            // Parse dimension concept identity:
            // 1. Get xxx:xxxx - agency and name
            // 2. Id string that located after version (x.x.x) and dot.
            if (preg_match('/=([\w_:]+)\([\d.~*]+\)\.([\w_]+)/', $full_indicator_concept_identity, $matches)) {
              $indicator_concept_identity = $matches[1];
              $indicator_concept_identity_id = $matches[2];
              [$indicator_concept_agency, $indicator_concept_name] = explode(':', $indicator_concept_identity);
              if (!empty($indicator_concept_agency) && !empty($indicator_concept_name)) {
                foreach ($dataset_structure['data']['conceptSchemes'] as $conceptScheme) {
                  if (
                    $conceptScheme['id'] == $indicator_concept_name &&
                    $conceptScheme['agency'] = $indicator_concept_agency
                  ) {
                    foreach ($conceptScheme['concepts'] as $concept) {
                      if ($concept['id'] == $indicator_concept_identity_id) {
                        $indicator_enumeration = $concept['coreRepresentation']['enumeration'];
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }

      $indicators_items = [];
      foreach ($dataset_glossaries as $dataset_glossary) {
        foreach ($dataset_glossary['links'] as $dataset_glossary_link) {
          if ($dataset_glossary_link['urn'] == $indicator_enumeration) {
            $indicators_items = $dataset_glossary['terms'];
          }
        }
      }

      $indicators_items = array_combine(array_column($indicators_items, 'id'), $indicators_items);

      if (empty($selected_indicators)) {
        $indicators = array_intersect_key($indicators_items, array_flip($indicators));
      }
      else {
        $indicators = array_intersect_key($indicators_items, array_flip($selected_indicators));
      }
    }

    return $indicators;
  }

}
