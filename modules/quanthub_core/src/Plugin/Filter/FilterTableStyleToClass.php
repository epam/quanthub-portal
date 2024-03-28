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
    if (!empty($text) && (is_string($text) || $text instanceof FilterProcessResult)) {
      $dom = new \DOMDocument();

      // Ignore warnings during HTML soup loading.
      // @todo refactor this code using
      // \Masterminds\HTML5 or \Drupal\Component\Utility\Html objects
      // and remove error control operator `@`.
      if (is_string($text)) {
        @$dom->loadHTML(mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8'));
      }
      if ($text instanceof FilterProcessResult) {
        @$dom->loadHTML(mb_convert_encoding($text->getProcessedText(), 'HTML-ENTITIES', 'UTF-8'));
      }

      $xpath = new \DOMXPath($dom);
      // Handle each table with special inline styles.
      foreach ($xpath->query('//table[@style]') as $table) {
        $classes = $table->getAttribute('class');
        // If table has style of 'border-width:0' add 'table-borderless' class.
        if (str_contains($table->getAttribute('style'), 'border-width:0')) {
          $classes .= ' borderless';
          $classes = trim($classes);
          $table->setAttribute('class', $classes);
        }

        // If table has a style of 'width:100%', add 'table-wide' class.
        if (str_contains($table->getAttribute('style'), 'width:100%')) {
          $classes .= ' table-wide';
          $classes = trim($classes);
          $table->setAttribute('class', $classes);
        }
        // Remove the style attribute.
        $table->removeAttribute('style');
      }

      // Handle each td tag with special inline styles.
      foreach ($xpath->query('//td[@style]') as $td) {
        $classes = $td->getAttribute('class');

        // If td has a style of 'border-width:0', add ' borderless' class.
        if (str_contains($td->getAttribute('style'), 'border-width:0')) {
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
