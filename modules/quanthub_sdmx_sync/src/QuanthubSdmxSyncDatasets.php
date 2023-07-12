<?php

namespace Drupal\quanthub_sdmx_sync;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Sdmx Sync Datasets service.
 *
 * Currently, we are syncing only last update date.
 */
class QuanthubSdmxSyncDatasets {

  /**
   * The last update annotation in dataset structure.
   */
  const LAST_UPDATE_ANNOTATION = 'lastUpdatedAt';

  /**
   * The dataset entity type.
   */
  const DATASET_ENTITY_TYPE = 'node';

  /**
   * The dataset content type.
   */
  const DATASETS_ENTITY_BUNDLE = 'dataset';

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
   * The datasets storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $datasetsStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(QuanthubSdmxClient $sdmx_client, EntityTypeManager $entity_type_manager, Connection $database) {
    $this->sdmxClient = $sdmx_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->datasetsStorage = $this->entityTypeManager->getStorage(self::DATASET_ENTITY_TYPE);
    $this->database = $database;
  }

  /**
   * Sync update date from sdmx client.
   */
  public function syncDatasetsUpdateDate() {
    foreach ($this->getDatasetUrns() as $dataset_nid => $dataset_urn) {
      if ($last_update_date = $this->getDatasetUpdateDate($dataset_urn)) {
        $last_update_date = strtotime($last_update_date);
        $dataset_entity = $this->datasetsStorage->load($dataset_nid);
        if ($dataset_entity instanceof EntityInterface) {
          $dataset_entity
            ->set('changed', $last_update_date)
            ->save();
        }
      }
    }
  }

  /**
   * Get last update date from sdmx for dataset by dataset urn.
   */
  public function getDatasetUpdateDate($dataset_urn) {
    $dataset_structure = $this->sdmxClient->getDasetStructure($dataset_urn);

    if (!empty($dataset_structure['data']['dataflows'])) {
      $dataflow_data = $dataset_structure['data']['dataflows'];
      foreach ($dataflow_data as $value) {
        foreach ($value['annotations'] as $annotation) {
          if ($annotation['id'] == self::LAST_UPDATE_ANNOTATION) {
            return $annotation['value'];
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Get Datasets Urns from published datasets.
   *
   * We are using just db select here for not loading full object of all nodes.
   *
   * @return array
   *   The list with node nids as key and quanthub_urn as value.
   */
  public function getDatasetUrns(): array {
    $query = $this->database->select('node_field_data', 'n');
    $query->condition('n.type', self::DATASETS_ENTITY_BUNDLE);
    $query->condition('n.status', 1);
    $query->leftJoin('node__field_quanthub_urn', 'fqu', 'fqu.entity_id = n.nid');
    $query->addField('n', 'nid');
    $query->addField('fqu', 'field_quanthub_urn_value');
    return $query->execute()->fetchAllKeyed();
  }

}
