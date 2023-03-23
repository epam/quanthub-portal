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
 *   default_thumbnail_filename = "power_bi.png"
 * )
 */
class PowerBI extends MediaSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {}

}
