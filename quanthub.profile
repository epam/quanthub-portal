<?php

/**
 * @file
 * Contains profile hooks.
 */

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Implements hook_modules_installed().
 *
 * @todo move to Recipes.
 */
function quanthub_modules_installed($modules) {
  // Don't import content on config sync.
  if (\Drupal::isConfigSyncing() || !\Drupal::config('quanthub.settings')->get('content.import')) {
    return;
  }
  if (in_array('default_content', $modules)) {
    \Drupal::service('default_content.importer')->importContent('quanthub');
  }
}

/**
 * Implements hook_entity_type_alter().
 */
function quanthub_entity_type_alter(array &$entity_types) {
  if (\Drupal::config('quanthub.settings')->get('content.title')) {
    $entity_types['node']->set('enable_base_field_custom_preprocess_skipping', TRUE);
  }
}

/**
 * Implements hook_entity_base_field_info_alter().
 */
function quanthub_entity_base_field_info_alter(&$fields, EntityTypeInterface $entity_type) {
  // Allow to configure node title in entity view form.
  if ($entity_type->id() == 'node' && \Drupal::config('quanthub.settings')->get('content.title')) {
    $fields['title']->setDisplayConfigurable('view', TRUE);
  }
}

/**
 * Implements hook_entity_bundle_field_info_alter().
 */
function quanthub_entity_bundle_field_info_alter(&$fields, EntityTypeInterface $entity_type, $bundle) {
  if (!empty($fields['field_quanthub_urn']) && \Drupal::config('quanthub.settings')->get('content.urn_versioning')) {
    $fields['field_quanthub_urn']->addPropertyConstraints('value', [
      'Regex' => [
        'pattern' => '/^\w+:\w+\(~\)$/',
        'message' => 'Wrong URN, use "AGENCY:ID(~)" format, pay attention that only latest ("~") version is supported.',
      ],
    ]);
  }
}

/**
 * Implements hook_preprocess_HOOK().
 */
function quanthub_preprocess_node(&$variables) {
  if (!empty($variables['content']['title']) && \Drupal::config('quanthub.settings')->get('content.title')) {
    if ($variables['page']) {
      $variables['content']['title'][0]['#prefix'] = '<h1 class="page-title">';
      $variables['content']['title'][0]['#suffix'] = '</h1>';
    }
    else {
      $variables['content']['title'][0]['#prefix'] = '<h2>';
      $variables['content']['title'][0]['#suffix'] = '</h2>';
    }
  }
}
