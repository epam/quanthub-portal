<?php

declare(strict_types=1);

namespace Drupal\quanthub_core\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to convert table from ckeditor style to classes.
 *
 * @Filter(
 *   id = "filter_table_style_to_class",
 *   title = @Translation("Convert table styles to class"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 * )
 */
class FilterTableStyleToClass extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    if (!empty($text) && is_array($text) || is_string($text)) {
      $dom = new \DOMDocument();
      $dom->loadHTML(mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

      $xpath = new \DOMXPath($dom);
      // Handle each table with special inline styles.
      foreach ($xpath->query('//table[@style]') as $table) {
        $classes = $table->getAttribute('class');
        // If table has style of 'border-width:0' add 'table-borderless' class.
        if (str_contains($table->getAttribute('style'), 'border-width:0')) {
          // Add 'table-borderless' class.
          $classes .= ' borderless';
          $classes = trim($classes);
          $table->setAttribute('class', $classes);
        }

        // If table has a style of 'width:100%', add 'table-wide' class.
        if (str_contains($table->getAttribute('style'), 'width:100%')) {
          // Add 'table-wide' class.
          $classes .= ' table-wide';
          $classes = trim($classes);
          $table->setAttribute('class', $classes);
        }
        // Remove the style attribute.
        $table->removeAttribute('style');
      }

      // Handle each table with special inline styles.
      foreach ($xpath->query('//td[@style]') as $td) {
        $classes = $td->getAttribute('class');

        // If table has a style of 'border-width:0', add 'td-borderless' class.
        if (str_contains($td->getAttribute('style'), 'border-width:0')) {
          // Add 'table-borderless' class.
          $classes .= ' borderless';
          $classes = trim($classes);
          $td->setAttribute('class', $classes);
        }
        // Remove the style attribute.
        $td->removeAttribute('style');
      }

      $new_html = $dom->saveHTML();

      return new FilterProcessResult($new_html);
    }
    else {
      return new FilterProcessResult($text);
    }
  }

}
