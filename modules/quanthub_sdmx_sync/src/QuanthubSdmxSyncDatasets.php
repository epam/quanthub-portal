<?php

namespace Drupal\quanthub_sdmx_sync;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\StringTranslation\TranslationInterface;
use Psr\Log\LoggerInterface;

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
   * The translation manager service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $translation;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    QuanthubSdmxClient $sdmx_client,
    EntityTypeManager $entity_type_manager,
    Connection $database,
    TranslationInterface $translation,
    LoggerInterface $logger
  ) {
    $this->sdmxClient = $sdmx_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->datasetsStorage = $this->entityTypeManager->getStorage(self::DATASET_ENTITY_TYPE);
    $this->database = $database;
    $this->translation = $translation;
    $this->logger = $logger;
  }

  /**
   * Sync update date from sdmx client.
   */
  public function syncDatasetsUpdateDate() {
    foreach ($this->getDatasetUrns() as $dataset_nid => $dataset_urn) {
      $update_dates = $this->getDatasetUpdateDates($dataset_urn);
      if (!empty($update_dates)) {
        if (strtotime($update_dates['UPDATED']) === FALSE) {
          $this->logger->info("For urn: $dataset_urn cannot parse date:" . $update_dates['UPDATED']);
        }
        else {
          $last_update_date = strtotime(trim($update_dates['UPDATED']));
          $dataset_entity = $this->datasetsStorage->load($dataset_nid);

          if (
            $dataset_entity instanceof EntityInterface &&
            $last_update_date != $dataset_entity->getCreatedTime()
          ) {
            $dataset_entity_languages = $dataset_entity->getTranslationLanguages();
            foreach ($dataset_entity_languages as $langcode => $language) {
              $dataset_entity_translation = $dataset_entity->getTranslation($langcode);

              // Updated only if updated date in sdmx changed.
              $metadata_value = $dataset_entity_translation->get('field_metadata')
                ->getValue();
              foreach ($metadata_value as $delta => $item) {
                if (!empty($update_dates['UPDATED'])) {
                  if ($langcode == 'en') {
                    if ($item['key'] == 'Updated') {
                      $metadata_value[$delta]['value'] = $update_dates['UPDATED'];
                    }
                  }
                  // Compare string translation to key in key/value field.
                  elseif ($item['key'] == $this->translation->getStringTranslation($langcode, 'Updated', '')) {
                    $metadata_value[$delta]['value'] = $update_dates['UPDATED'];
                  }
                }
                if (!empty($update_dates['NEXT_UPDATE'])) {
                  if ($langcode == 'en') {
                    if ($item['key'] == 'Next Update') {
                      $metadata_value[$delta]['value'] = $update_dates['NEXT_UPDATE'];
                    }
                  }
                  // Compare string translation to key in key/value field.
                  elseif ($item['key'] == $this->translation->getStringTranslation($langcode, 'Next Update', '')) {
                    $metadata_value[$delta]['value'] = $update_dates['NEXT_UPDATE'];
                  }
                }
              }

              $dataset_entity->getTranslation($langcode)
                ->set('field_metadata', $metadata_value)
                ->set('created', $last_update_date)
                ->setSyncing(TRUE)
                ->save();
            }
          }
        }
      }
    }
  }

  /**
   * Get last update date from sdmx for dataset by dataset urn.
   */
  public function getDatasetUpdateDates($dataset_urn) {
    $filtered_data = $this->sdmxClient->getDatasetFilteredData($dataset_urn, 'limit=1');
    $data = [];

    if (!empty($filtered_data['data']['structures'][0]['attributes']['dataSet'])) {
      $dataset_attributes = $filtered_data['data']['structures'][0]['attributes']['dataSet'];
      foreach ($dataset_attributes as $dataset_attribute_key => $dataset_attribute) {
        if ($dataset_attribute['id'] == 'UPDATED') {
          $updated_dataset_attr_index = $dataset_attribute_key;
        }
        if ($dataset_attribute['id'] == 'NEXT_UPDATE') {
          $next_update_dataset_attr_index = $dataset_attribute_key;
        }
      }

      if (!empty($updated_dataset_attr_index) && !empty($filtered_data['data']['dataSets'][0]['attributes'][$updated_dataset_attr_index][0])) {
        $data['UPDATED'] = $filtered_data['data']['dataSets'][0]['attributes'][$updated_dataset_attr_index][0];
      }
      if (!empty($next_update_dataset_attr_index) && !empty($filtered_data['data']['dataSets'][0]['attributes'][$next_update_dataset_attr_index][0])) {
        $data['NEXT_UPDATE'] = $filtered_data['data']['dataSets'][0]['attributes'][$next_update_dataset_attr_index][0];
      }
    }

    $this->logger->info("For $dataset_urn found dates:" . '<pre>' . print_r($data, 1) . '</pre>');

    return $data;
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
