<?php

namespace Drupal\quanthub_core\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\powerbi_embed\Plugin\Field\FieldType\PowerBIEmbedField;

/**
 * Extending the PowerBIEmbedField.
 *
 * @FieldType(
 *   id = "quanthub_powerbi_embed",
 *   module = "quanthub_core",
 *   label = @Translation("QuantHub PowerBI Embed report"),
 *   category = @Translation("Reference"),
 *   description = @Translation("This field type stores PowerBI Embed report reference information."),
 *   default_widget = "quanthub_powerbi_embed_widget",
 *   default_formatter = "quanthub_powerbi_embed_formatter",
 *   column_groups = {
 *     "report_extra_datasets" = {
 *       "label" = @Translation("Report extra datasets"),
 *       "translatable" = TRUE
 *     },
 *     "report_page" = {
 *       "label" = @Translation("Report page"),
 *       "translatable" = TRUE
 *     },
 *     "report_visual" = {
 *       "label" = @Translation("Report visual"),
 *       "translatable" = TRUE
 *     },
 *   },
 * )
 */
class QuantHubPowerBIEmbedField extends PowerBIEmbedField {

  /**
   * {@inheritDoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['report_extra_datasets'] = DataDefinition::create('string')
      ->setLabel(t('Report extra datasets'))
      ->setDescription(t('PowerBI Report extra datasets'));

    $properties['report_page'] = DataDefinition::create('string')
      ->setLabel(t('Report page'))
      ->setDescription(t('PowerBI Report page'));

    $properties['report_visual'] = DataDefinition::create('string')
      ->setLabel(t('Report visual'))
      ->setDescription(t('PowerBI Report visual'));

    return $properties;
  }

  /**
   * {@inheritDoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    $schema = parent::schema($field_definition);

    $columns = [
      'report_extra_datasets' => [
        'type' => 'varchar',
        'length' => 2048,
      ],
      'report_page' => [
        'type' => 'varchar',
        'length' => 1024,
      ],
      'report_visual' => [
        'type' => 'varchar',
        'length' => 1024,
      ],
    ];

    $schema['columns'] += $columns;

    return $schema;
  }

}
