<?php

namespace Drupal\quanthub_indicator\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\quanthub_sdmx_sync\QuanthubSdmxClient;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processor for rewriting fields according to indicator ID.
 *
 * @SearchApiProcessor(
 *   id = "quanthub_indicator_processor",
 *   label = @Translation("Quanthub Indicator Processor"),
 *   description = @Translation(""),
 *   stages = {
 *     "alter_items" = 0,
 *     "pre_index_save" = 0,
 *     "preprocess_index" = -10,
 *     "preprocess_query" = -10,
 *   },
 * )
 */
class QuanthubIndicatorProcessor extends FieldsProcessorPluginBase {

  /**
   * Explorer route name.
   */
  const EXPLORER_ROUTE = 'quanthub_datasetexplorer.explorer';

  /**
   * The SDMX client.
   *
   * @var \Drupal\quanthub_sdmx_sync\QuanthubSdmxClient
   */
  protected $sdmxClient;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Loaded indicators data from SDMX.
   *
   * @var array
   */
  protected $loadedIndicators = [];

  /**
   * Datasets dimensions from SDMX.
   *
   * @var array
   */
  protected $datasetsDimensions = [];

  /**
   * Indexing entity.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entity;

  /**
   * The dataset urn.
   *
   * @var bool|string
   */
  protected $datasetUrn = FALSE;

  /**
   * The indicator ID.
   *
   * @var bool|string
   */
  protected $indicatorId = FALSE;

  /**
   * The langcode string.
   *
   * @var string|null
   */
  private $langcode;

  /**
   * The language object.
   *
   * @var \Drupal\Core\Language\LanguageInterface|null
   */
  private ?LanguageInterface $language;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->setSdmxClient($container->get('sdmx_client'));
    $processor->setRenderer($container->get('renderer'));
    $processor->setLanguageManager($container->get('language_manager'));

    return $processor;
  }

  /**
   * Method DI.
   *
   * @param \Drupal\quanthub_sdmx_sync\QuanthubSdmxClient $sdmx_client
   *   The SDMX client.
   */
  protected function setSdmxClient(QuanthubSdmxClient $sdmx_client) {
    $this->sdmxClient = $sdmx_client;
  }

  /**
   * Method DI.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The rendered service.
   */
  protected function setRenderer(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * Method DI.
   *
   * @param \Drupal\Core\Language\LanguageManager $languageManager
   *   The language manager service.
   */
  protected function setLanguageManager(LanguageManager $languageManager) {
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items) {
    $this->loadIndicators($items);

    // Annoyingly, this doc comment is needed for PHPStorm. See
    // http://youtrack.jetbrains.com/issue/WI-23586
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      // Our search processor work only for indicator.
      if ($item->getOriginalObject()->getValue()->getType() == 'indicator') {
        $this->langcode = $item->getLanguage();
        $this->language = $this->languageManager->getLanguage($this->langcode);

        $config_original_language = $this->languageManager->getConfigOverrideLanguage();
        $this->languageManager->setConfigOverrideLanguage($this->language);

        $this->entity = $item->getOriginalObject()->getValue()->getTranslation($this->langcode);

        $this->datasetUrn = $this->entity->field_dataset->first()->entity->field_quanthub_urn->getString();
        $this->indicatorId = $item->getExtraData('indicator_id') ?: FALSE;

        if (
          !empty($this->indicatorId) &&
          empty($this->datasetsDimensions[$this->datasetUrn])
        ) {
          $this->datasetsDimensions[$this->datasetUrn] = $this->sdmxClient->getDimensions($this->datasetUrn);
        }
        foreach ($item->getFields() as $field) {
          $this->processField($field);
        }

        $this->languageManager->setConfigOverrideLanguage($config_original_language);
      }
    }
  }

  /**
   * Process the field.
   */
  protected function processField(FieldInterface $field) {
    if ($this->entity->getType() == 'indicator') {
      parent::processField($field);

      $dataset_entity = $this->entity->field_dataset
        ->first()
        ->get('entity')
        ->getTarget()
        ->getValue();

      if ($dataset_entity->hasTranslation($this->langcode)) {
        $dataset_entity_localized = $dataset_entity->getTranslation($this->langcode);
      }

      if ($this->indicatorId && !empty($dataset_entity_localized)) {
        if (method_exists($field, 'getFieldIdentifier')) {
          switch ($field->getFieldIdentifier()) {
            case 'rendered_item':
              $this->processRenderedItemField($field, $dataset_entity_localized);
              break;

            case 'title':
              if ($this->langcode && !empty($this->loadedIndicators[$this->datasetUrn][$this->indicatorId]['names'][$this->langcode])) {
                $new_field_values[] = new TextValue($this->loadedIndicators[$this->datasetUrn][$this->indicatorId]['names'][$this->langcode]);
                $field->setValues($new_field_values);
              }
              break;

            case 'field_topics':
              $topics = [];
              foreach ($dataset_entity_localized->field_topics->referencedEntities() as $referencedEntity) {
                if ($referencedEntity->hasTranslation($this->langcode)) {
                  $topics[] = $referencedEntity->getTranslation($this->langcode)
                    ->id();
                }
              }
              $field->setValues($topics);
              break;

            case 'topics_name':
              $topic_names = [];
              foreach ($dataset_entity_localized->field_topics->referencedEntities() as $referencedEntity) {
                if ($referencedEntity->hasTranslation($this->langcode)) {
                  $topic_names[] = new TextValue($referencedEntity->getTranslation($this->langcode)->name->getString());
                }
              }
              $field->setValues($topic_names);
              break;
          }
        }
      }
    }
  }

  /**
   * Process rendered item field.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The search field item.
   * @param \Drupal\Core\Entity\EntityInterface $dataset_entity
   *   The dataset content entity.
   */
  public function processRenderedItemField(FieldInterface $field, EntityInterface $dataset_entity) {
    if ($this->indicatorId) {
      $field_values = $field->getValues();

      if ($this->langcode && !empty($this->loadedIndicators[$this->datasetUrn][$this->indicatorId])) {
        if (!empty($this->loadedIndicators[$this->datasetUrn][$this->indicatorId]['names'][$this->langcode])) {
          $indicator_title = $this->loadedIndicators[$this->datasetUrn][$this->indicatorId]['names'][$this->langcode];
        }
        else {
          $indicator_title = $this->loadedIndicators[$this->datasetUrn][$this->indicatorId]['name'];
        }
        $indicator_uri = Url::fromRoute(
          self::EXPLORER_ROUTE,
          [],
          [
            'query' => [
              'urn' => $this->datasetUrn,
              'filter' => $this->getDimensionsFilterUrl($this->datasetUrn),
            ],
            'language' => $this->language,
          ]
        );

        $dataset_uri = $dataset_entity->toLink(
          NULL,
          'canonical',
          [
            'class' => [
              'indicator-dataset',
              'indicator-dataset-link',
            ],
          ]
        )->toString();

        $topics_links = [];
        if (!$dataset_entity->field_topics->isEmpty()) {
          foreach ($dataset_entity->field_topics->referencedEntities() as $referencedEntity) {
            if ($referencedEntity->hasTranslation($this->langcode)) {
              $topics_links[] = $referencedEntity->getTranslation($this->langcode)->toLink();
            }
          }
        }

        $dataset_bundle_label = NodeType::load('dataset')->label();
        $indicator_bundle_label = NodeType::load('indicator')->label();

        $indicator_renderable = [
          '#theme' => 'quanthub_indicator',
          '#indicator_title' => $indicator_title,
          '#indicator_uri' => $indicator_uri,
          '#indicator_dataset_link' => $dataset_uri,
          '#indicator_dataset_bundle_label' => $dataset_bundle_label,
          '#indicator_bundle_label' => $indicator_bundle_label,
          '#indicator_topics' => $topics_links,
          '#indicator_dataset_urn' => $this->datasetUrn,
          '#indicator_data_value' => $this->getDimensionsFilterUrl($this->datasetUrn),
          '#indicator_parameter' => $this->entity->field_indicator_parameter->getString() ?: QuanthubSdmxClient::STRUCTURE_DIMENSION_ID,
        ];

        $rendered_indicator = $this->renderer->renderPlain($indicator_renderable);

        $field_values[0]->setText($rendered_indicator);
        $field_values[0]->setOriginalText($rendered_indicator);
        $field->setValues($field_values);
      }
    }
  }

  /**
   * Build dimensions url for dataset by urn.
   *
   * @param string $urn
   *   The dataset urn string.
   *
   * @return string
   *   The string of dataset dimensions.
   */
  public function getDimensionsFilterUrl(string $urn) {
    $dimensions_url = '';
    $indicator_dimension_id = $this->entity->field_indicator_parameter->getString();
    if (empty($indicator_dimension_id)) {
      $indicator_dimension_id = QuanthubSdmxClient::STRUCTURE_DIMENSION_ID;
    }
    if (!empty($this->datasetsDimensions[$urn])) {
      $dimensions_parts = [];
      foreach ($this->datasetsDimensions[$urn] as $key => $dimension) {
        // @todo make configurable INDICATOR.
        $dimensions_parts[$key] = match ($dimension) {
          $indicator_dimension_id => $this->indicatorId,
          default => '*',
        };
      }
      $dimensions_url = implode('.', $dimensions_parts);
    }

    return $dimensions_url;
  }

  /**
   * Load indicators data to the object space.
   */
  public function loadIndicators($items) {
    $datasets_indicators_data = [];

    // Find all datasets from indicator search items.
    foreach ($items as $item) {
      $entity = $item->getOriginalObject()->getValue();

      if ($entity->getType() == 'indicator') {
        $indicator_dimension_id = $entity->field_indicator_parameter->getString();
        $dataset_urn = $entity
          ->field_dataset
          ->first()
          ->get('entity')
          ->getTarget()
          ->getValue()
          ->field_quanthub_urn
          ->getString();

        if ($item->getExtraData('indicator_id')) {
          $datasets_indicators_data[$dataset_urn][] = $item->getExtraData('indicator_id');
          $indicator_dimension_ids[$dataset_urn] = $indicator_dimension_id;
        }
      }
    }

    if ($datasets_indicators_data) {
      foreach ($datasets_indicators_data as $datasets_urn => $dataset_indicators) {
        $this->loadedIndicators[$datasets_urn] = $this->sdmxClient->datasetIndicators(
          $datasets_urn,
          $indicator_dimension_ids[$dataset_urn],
          $dataset_indicators
        );
      }
    }
  }

  /**
   * Need to remove items with not existed indicators, on indexing.
   */
  public function alterIndexedItems(array &$items) {
    parent::alterIndexedItems($items);
    $this->loadIndicators($items);

    foreach ($items as $key => $item) {
      $entity = $item->getOriginalObject()->getValue();

      if ($entity->getType() == 'indicator') {
        $dataset_urn = $entity
          ->field_dataset
          ->first()
          ->get('entity')
          ->getTarget()
          ->getValue()
          ->field_quanthub_urn
          ->getString();

        if (
          empty($this->loadedIndicators) ||
          empty($this->loadedIndicators[$dataset_urn][$item->getExtraData('indicator_id')])
        ) {
          unset($items[$key]);
        }
      }
    }
  }

}
