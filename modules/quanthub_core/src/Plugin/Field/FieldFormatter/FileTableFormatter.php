<?php

namespace Drupal\quanthub_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\Plugin\Field\FieldFormatter\TableFormatter;

/**
 * Plugin implementation of the 'Table of files (size in MB)' formatter.
 *
 * @FieldFormatter(
 *   id = "quanthub_core_file_table",
 *   label = @Translation("Table of files (size in MB)"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class FileTableFormatter extends TableFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = parent::viewElements($items, $langcode);
    // Unset the header labels because we don't need them.
    unset($element[0]['#header']);

    /** @var \Drupal\file\Entity\File $file */
    $file = $element[0]['#rows'][0][0]['data']['#file'];
    $element[0]['#rows'][0][1]['data'] = $this->bytesToMegabyte($file->getSize());

    return $element;
  }

  /**
   * Convert bytes to Megabyte format.
   *
   * @return string
   *   Return the Megabyte number with the suffix MB.
   */
  public function bytesToMegabyte($bytes): string {
    $bytes /= 1048576;
    return round($bytes, 2) . ' MB';
  }

}
