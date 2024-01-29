<?php

namespace Drupal\quanthub_core\EditorXssFilter;

use Drupal\Component\Utility\Html;
use Drupal\editor\EditorXssFilter\Standard;

/**
 * Defines the standard text editor with styles XSS filter.
 *
 * @todo remove when solved https://www.drupal.org/project/drupal/issues/3109650
 */
class StandardWithStyles extends Standard {

  /**
   * Processes a string of HTML attributes.
   *
   * @param string $attributes
   *   The html attribute to process.
   *
   * @return array
   *   Cleaned up version of the HTML attributes.
   */
  protected static function attributes($attributes) {
    /** @var array $attributes_array */
    $attributes_array = parent::attributes($attributes);

    if (preg_match('/^([-a-zA-Z][-a-zA-Z0-9]*)/', $attributes, $match)) {
      $attribute_name = strtolower($match[1]);
      if ($attribute_name == 'style') {
        $html_dom = Html::load("<span " . $attributes . "></span>");
        $span_tags = $html_dom->getElementsByTagName('span');
        /** @var \DOMElement $span_tag */
        foreach ($span_tags as $span_tag) {
          if ($span_tag->hasAttribute('style')) {
            $attributes_array[] = 'style="' . $span_tag->getAttribute('style') . '"';
          }
        }
      }
    }

    return $attributes_array;
  }

}
