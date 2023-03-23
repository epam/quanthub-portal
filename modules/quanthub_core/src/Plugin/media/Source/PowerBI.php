<?php

namespace Drupal\quanthub_core\Plugin\media\Source;

use Drupal\media\MediaSourceBase;

/**
 * Power BI entity media source.
 *
 * @see \Drupal\file\FileInterface
 *
 * @MediaSource(
 *   id = "power_bi",
 *   label = @Translation("Power BI"),
 *   description = @Translation("Power BI media source"),
 *   allowed_field_types = {"quanthub_powerbi_embed"},
 * )
 */
class PowerBI extends MediaSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {}

}
