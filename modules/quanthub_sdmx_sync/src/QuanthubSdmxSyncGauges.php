<?php

namespace Drupal\quanthub_sdmx_sync;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Sdmx Sync Gauges service.
 */
class QuanthubSdmxSyncGauges {

  /**
   * The gauges entity type.
   */
  const GAUGES_ENTITY_TYPE = 'node';

  /**
   * The gauges content type.
   */
  const GAUGES_ENTITY_BUNDLE = 'gauge';

  /**
   * The Sdmx Client.
   *
   * @var \Drupal\quanthub_sdmx_sync\SdmxClient
   */
  private $sdmxClient;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * The gauges storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $gaugesStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(QuanthubSdmxClient $sdmx_client, EntityTypeManager $entity_type_manager, Connection $database) {
    $this->sdmxClient = $sdmx_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->gaugesStorage = $this->entityTypeManager->getStorage(self::GAUGES_ENTITY_TYPE);
    $this->database = $database;
  }

  /**
   * Get published gauges.
   */
  public function getGaugesData() {
    $query = $this->database->select('node_field_data', 'n');
    $query->condition('n.type', self::GAUGES_ENTITY_BUNDLE);
    $query->condition('n.status', 1);
    $query->join('node__field_dataset', 'nfd', 'nfd.entity_id = n.nid');
    $query->join('node__field_quanthub_urn', 'nfqu', 'nfqu.entity_id = nfd.field_dataset_target_id');
    $query->join('node__field_gauge_filter', 'nfqf', 'nfqf.entity_id = n.nid');
    $query->addField('n', 'nid');
    $query->addField('nfqf', 'field_gauge_filter_value', 'filter');
    $query->addField('nfqu', 'field_quanthub_urn_value', 'quanthub_urn');
    return $query->execute()->fetchAll();
  }

  /**
   * Sync gauges data.
   */
  public function syncGauages() {
    $gauges_data = $this->getGaugesData();
    foreach ($gauges_data as $gauge_data) {
      $dataset_data = $this->sdmxClient->getDatasetFilteredData(
        $this->transformUrn(
          $gauge_data->quanthub_urn
        ),
        $gauge_data->filter
      );

      if (
        !empty($dataset_data['data']['dataSets'][0]['series']) &&
        !empty($dataset_data['data']['structures'][0]['dimensions']['observation'][0]['values'])
      ) {
        $structure_observation = $dataset_data['data']['structures'][0]['dimensions']['observation'][0]['values'];
        $last_structure_observation = end($structure_observation)['value'];

        $structure_series = $dataset_data['data']['dataSets'][0]['series'];
        $structure_observations = array_column($structure_series, 'observations');
        $structure_observations = end($structure_observations);
        $last_serie_value = end($structure_observations);
        $last_serie_value = end($last_serie_value);
        $this->updateGauge(
          $gauge_data->nid,
          $last_structure_observation,
          $last_serie_value
        );
      }
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
    // @todo need refactor and move to other space perhaps trait.
    // Changed divider in string to slash for url if found version.
    if (preg_match("/\([0-9.]/", $dataset_urn)) {
      $dataset_urn_url = str_replace([':', '('], '/', $dataset_urn);
      $dataset_urn_url = str_replace(')', '', $dataset_urn_url);
    }
    else {
      // We need latest dataset version, if version isn't specified.
      $dataset_urn_url = str_replace('(*)', '/latest', $dataset_urn);
    }

    return $dataset_urn_url;
  }

  /**
   * Update gauge node.
   *
   * @param int $nid
   *   The node id.
   * @param string $period
   *   The period.
   * @param string $value
   *   The value.
   */
  public function updateGauge(int $nid, string $period, string $value) {
    $entity = $this->entityTypeManager->getStorage('node')->load($nid);

    $entity->set('field_gauge_period', $period);
    $entity->set('field_gauge_value', $value);

    $entity->save();
  }

}
