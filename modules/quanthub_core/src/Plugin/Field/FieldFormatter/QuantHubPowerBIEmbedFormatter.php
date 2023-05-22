<?php

namespace Drupal\quanthub_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\quanthub_core\PowerBIEmbedConfigs;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Formatter for Power BI Embed field type.
 *
 * @FieldFormatter(
 *   id = "quanthub_powerbi_embed_formatter",
 *   label = @Translation("QuantHub PowerBI Embed report"),
 *   field_types = {
 *     "quanthub_powerbi_embed"
 *   }
 * )
 */
class QuantHubPowerBIEmbedFormatter extends FormatterBase {

  /**
   * The logger service definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\quanthub_core\PowerBIEmbedConfigs
   */
  protected $powerBIEmbedConfigs;

  /**
   * Static method create for factory.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('powerbi_embed_configs'),
    );
  }

  /**
   * Construct a QuantHubPowerBIEmbedFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory service.
   * @param \Drupal\quanthub_core\PowerBIEmbedConfigs $powerBIEmbedConfigs
   *   The PowerBIEmbedConfigs object.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, LoggerChannelFactoryInterface $logger_factory, PowerBIEmbedConfigs $powerBIEmbedConfigs) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->loggerFactory = $logger_factory;
    $this->powerBIEmbedConfigs = $powerBIEmbedConfigs;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $workspace_id = $this->powerBIEmbedConfigs->getWorkspaceID();

    foreach ($items as $delta => $item) {
      if (isset($item->report_id) && !empty($item->report_id)) {
        $embed_config = $this->powerBIEmbedConfigs->getPowerEmbedConfig($item->report_id, $item->report_extra_datasets);

        $embed_token = $embed_config["embed_token"];
        $embed_url = $embed_config["embed_url"];

        if (isset($embed_token)) {
          // @todo Implement DI.
          $expiration = DateTimePlus::createFromFormat('Y-m-d\TH:i:s\Z', $embed_token["expiration"]);
          $max_age = $expiration->getTimestamp() - (new DateTimePlus())->getTimestamp() - 3;
          if ($max_age < 0) {
            $max_age = 0;
          }

          $embed_type = 'report';
          $embed_id = $item->report_id;
          if (isset($item->report_page) && !empty($item->report_page)) {
            $embed_id = $embed_id . '_' . preg_replace('/\s+/', '_', $item->report_page);
          }

          if (isset($item->report_visual) && !empty($item->report_visual)) {
            $embed_id = $embed_id . '_' . preg_replace('/\s+/', '_', $item->report_visual);
            $embed_type = 'visual';
          }

          $elements[$delta] = [
            '#embed_id' => $embed_id,
            '#embed_type' => $embed_type,
            '#field_name' => $item->getParent()->getName(),
            '#report_id' => $item->report_id,
            '#report_width' => $item->report_width,
            '#report_height' => $item->report_height,
            '#report_title' => $item->report_title,
            '#report_page' => $item->report_page,
            '#report_visual' => $item->report_visual,
            '#workspace_id' => $workspace_id,
            '#token_expiration' => $embed_token["expiration"],
            '#extra_datasets' => $item->report_extra_datasets,
            '#token' => $embed_token["token"],
            '#embed_url' => $embed_url,
            '#theme' => 'powerbi_embed_formatter',
            '#cache' => [
              'tags' => ['powerbi_embed:token'],
              'max-age' => $max_age,
            ],
          ];
        }
      }
    }
    return $elements;
  }

}
