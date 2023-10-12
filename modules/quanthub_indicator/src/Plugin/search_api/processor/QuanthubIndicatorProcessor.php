<?php

namespace Drupal\quanthub_indicator\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\quanthub_sdmx_sync\QuanthubSdmxClient;
use Drupal\search_api\Item\FieldInterface;
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
        $this->entity = $item->getOriginalObject()->getValue();
        $this->datasetUrn = $this->entity->field_dataset->first()->entity->field_quanthub_urn->getString();
        $this->indicatorId = $item->getExtraData('indicator_id') ?: FALSE;

        if (
          !empty($this->indicatorId) &&
          empty($this->datasetsDimensions[$this->datasetUrn])
        ) {
          $this->datasetsDimensions[$this->datasetUrn] = $this->sdmxClient->getDimensions($this->datasetUrn);
        }
        foreach ($item->getFields() as $name => $field) {
          if ($this->testField($name, $field)) {
            $this->processField($field);
          }
        }
      }
    }
  }

  /**
   * Process the field.
   */
  protected function processField(FieldInterface $field) {
    parent::processField($field);
    $langcode = $this->entity->get('langcode')->getString();

    $dataset_entity = $this->entity->field_dataset
      ->first()
      ->get('entity')
      ->getTarget()
      ->getValue();

    if ($this->indicatorId) {
      $field_values = $field->getValues();

      switch ($field->getFieldIdentifier()) {
        case 'rendered_item':
          $this->processRenderedItemField($field, $dataset_entity);
          break;

        case 'title':
          if ($langcode) {
            $field_values[0]->setText($this->loadedIndicators[$this->indicatorId]['names'][$langcode]);
            $field_values[0]->setOriginalText($this->loadedIndicators[$this->indicatorId]['names'][$langcode]);
            $field->setValues($field_values);
          }
          break;
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
      $langcode = $this->entity->get('langcode')->getString();
      $field_values = $field->getValues();

      if ($langcode) {
        $indicator_title = $this->loadedIndicators[$this->datasetUrn][$this->indicatorId]['names'][$langcode];
        $indicator_uri = Url::fromRoute(
          self::EXPLORER_ROUTE,
          [],
          [
            'query' => [
              'urn' => $this->datasetUrn,
              'filter' => $this->getDimensionsFilterUrl($this->datasetUrn),
            ],
            'language' => $this->languageManager->getLanguage($langcode),
          ]
        )->toUriString();

        $dataset_uri = Url::fromRoute(
          'entity.node.canonical',
          ['node' => $dataset_entity->id()],
          ['language' => $this->languageManager->getLanguage($langcode)]
        )->toUriString();

        $indicator_renderable = [
          '#theme' => 'quanthub_indicator',
          '#indicator_title' => $indicator_title,
          '#indicator_uri' => $indicator_uri,
          '#indicator_langcode' => $langcode,
          '#indicator_dataset' => $dataset_entity,
          '#indicator_dataset_uri' => $dataset_uri,
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
    if (!empty($this->datasetsDimensions[$urn])) {
      $dimensions_parts = [];
      foreach ($this->datasetsDimensions[$urn] as $key => $dimension) {
        // @todo make configurable INDICATOR.
        $dimensions_parts[$key] = match ($dimension) {
          'INDICATOR' => $this->indicatorId,
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

}
