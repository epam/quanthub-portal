<?php

declare(strict_types=1);

namespace Drupal\quanthub_core\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to convert table from ckeditor style to classes.
 *
 * By default, the use of style attributes is prohibited for all
 * text formats (except Full HTML). In order to allow the user to replace
 * the basic styles in the table (for example, alignment),
 * we replace the style with the corresponding class.
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
        $this->replaceStyle($table);
      }

      // Handle each td tag with special inline styles.
      foreach ($xpath->query('//td[@style]') as $td) {
        $this->replaceStyle($td);
      }

      $new_html = $dom->saveHTML();

      return new FilterProcessResult($new_html);
    }
    else {
      return new FilterProcessResult($text);
    }
  }

  /**
   * Replace style with class
   *
   * @param \DOMNode $node
   *   A DOMNode object.
   */
  protected function replaceStyle(\DOMNode $node) {
    $classes = array_filter(explode(' ', $node->getAttribute('class')));
    $styles = array_filter(explode(';', $node->getAttribute('style')));

    foreach ($styles as $style) {
      // If Node has style of 'border-width:0' add 'table-borderless' class.
      if (str_contains($style, 'border-width:0') || str_contains($style, 'border:0')) {
        $classes[] = 'borderless';
      }

      // If $node has a style of 'width:100%', add 'table-wide' class.
      elseif (str_contains($style, 'width:100%')) {
        $classes[] = 'table-wide';
      }

      // If $node has a style of 'vertical-align', add 'vertical-{value}' class.
      elseif (str_contains($style, 'vertical-align')) {
        [, $styleValue] = explode(':', str_replace(';', '', $style));
        $classes[] = 'vertical-' . $styleValue;
      }

      // If $node has a style of 'text-align', add 'text-{value}' class.
      elseif (str_contains($style, 'text-align')) {
        [, $styleValue] = explode(':', str_replace(';', '', $style));
        $classes[] = 'text-' . $styleValue;
      }
    }
    $node->setAttribute('class', implode(' ', $classes));
    $node->removeAttribute('style');
  }

}
