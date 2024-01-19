<?php

namespace Drupal\quanthub_codelists\Controller;

use Drupal\Core\Block\BlockManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Main controller for Code Lists.
 */
final class CodeLists extends ControllerBase {

  /**
   * Default System Language.
   */
  const SYSTEM_LANGUAGE = 'en';

  /**
   * Taxonomy term vocabulary name with code lists titles.
   */
  const CODE_LISTS_VOCAB = 'codelists_titles';

  /**
   * The block manager service definition.
   *
   * @var \Drupal\Core\Block\BlockManager
   */
  protected $pluginManagerBlock;

  /**
   * The current user service definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    BlockManager $plugin_manager_block,
    AccountProxy $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager
  ) {
    $this->pluginManagerBlock = $plugin_manager_block;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * Build codelists block.
   */
  public function codelists(Request $request) {
    $block_manager = $this->pluginManagerBlock;

    // You can hard code configuration or you load from settings.
    $config = [];
    $plugin_block = $block_manager->createInstance('quanthub_codelists_block', $config);

    // Some blocks might implement access check.
    $access_result = $plugin_block->access($this->currentUser);

    // Return empty render array if user doesn't have access.
    // $access_result can be boolean or an AccessResult class.
    if (is_object($access_result) && $access_result->isForbidden() || is_bool($access_result) && !$access_result) {
      // You might need to add some cache tags/contexts.
      return [];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['codelists'],
      ],
      'element-content' => $plugin_block->build(),
      '#weight' => 0,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    $build['element-content']['#attached']['drupalSettings']['mode'] = 'codelists';
    $build['element-content']['#attached']['drupalSettings']['query'] = $request->query->all();
    $build['element-content']['#attached']['drupalSettings']['codelist_titles'] = $this->getCodeListTitles();

    return $build;
  }

  /**
   * Returns translatable page title.
   */
  public function getTitle(): string {
    return $this->t('Code Lists');
  }

  /**
   * Get codelists titles with translation.
   */
  protected function getCodeListTitles() {
    $tree_map = [];

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $codelists_titles_tree = $term_storage->loadTree(
      self::CODE_LISTS_VOCAB,
      0,
      NULL,
      TRUE
    );

    $languages = $this->languageManager->getLanguages();

    if (!empty($codelists_titles_tree)) {
      foreach ($codelists_titles_tree as $item) {
        if ($item->hasTranslation(self::SYSTEM_LANGUAGE)) {
          $key_title = $item->getTranslation(self::SYSTEM_LANGUAGE)->getName();
          foreach ($languages as $key => $value) {
            $tree_map[$key_title][$key] = $item
              ->getTranslation($key)
              ->getName();
          }
        }
      }
    }

    return $tree_map;
  }

}
