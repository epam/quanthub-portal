<?php

namespace Drupal\quanthub_visualization\Plugin\media\Source;

use Drupal\media\MediaSourceBase;

/**
 * Power BI entity media source.
 *
 * @see \Drupal\file\FileInterface
 *
 * @MediaSource(
 *   id = "qh_visualization",
 *   label = @Translation("Quanthub Visualization"),
 *   description = @Translation("Quanthub Visualization media source"),
 *   allowed_field_types = {"json_native_binary"},
 * )
 */
class QuanthubVisualizationSource extends MediaSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {}

}
