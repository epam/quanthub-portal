<?php

namespace Drupal\quanthub_indicator\Plugin\search_api\datasource;

use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;

/**
 * Override .
 *
 * @SearchApiDatasource(
 *   id = "entity",
 *   deriver = "Drupal\search_api\Plugin\search_api\datasource\ContentEntityDeriver"
 * )
 */
class QuanthubContentEntity extends ContentEntity {

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    $allowed_languages = $this->getLanguages();

    $entity_ids = [];
    foreach ($ids as $item_id) {
      $indicator_node_flag = FALSE;
      $pos = strrpos($item_id, ':');
      // This can only happen if someone passes an invalid ID, since we always
      // include a language code. Still, no harm in guarding against bad input.
      if ($pos === FALSE) {
        continue;
      }
      $entity_id = substr($item_id, 0, $pos);
      $langcode = substr($item_id, $pos + 1);
      if (str_contains($langcode, '_indicator')) {
        $exploded_langcode = explode('_indicator', $langcode);
        if (!empty($exploded_langcode[0])) {
          $langcode = $exploded_langcode[0];
        }
        $indicator_node_flag = TRUE;
      }
      if (isset($allowed_languages[$langcode])) {
        if ($indicator_node_flag) {
          $entity_ids[$entity_id][$item_id] = $langcode . '_indicator' . $exploded_langcode[1];
        }
        else {
          $entity_ids[$entity_id][$item_id] = $langcode;
        }
      }
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
    $entities = $this->getEntityStorage()->loadMultiple(array_keys($entity_ids));
    $items = [];
    $allowed_bundles = $this->getBundles();

    foreach ($entity_ids as $entity_id => $langcodes) {
      if (empty($entities[$entity_id]) || !isset($allowed_bundles[$entities[$entity_id]->bundle()])) {
        continue;
      }
      foreach ($langcodes as $item_id => $langcode) {

        $filtered_langcode = $langcode;
        if (str_contains($langcode, '_indicator')) {
          $exploded_langcode = explode('_indicator', $langcode);
          if (!empty($exploded_langcode[0])) {
            $filtered_langcode = $exploded_langcode[0];
          }
        }

        if ($entities[$entity_id]->hasTranslation($filtered_langcode)) {
          $items[$item_id] = $entities[$entity_id]->getTranslation($filtered_langcode)->getTypedData();
        }
      }
    }

    return $items;
  }

}
