<?php

namespace Drupal\quanthub\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to display the page title.
 */
#[Block(
  id: "page_title_extended",
  admin_label: new TranslatableMarkup("Page title (extended)"),
  forms: [
    'settings_tray' => FALSE,
  ]
)]
class PageTitleExtendedBlock extends BlockBase implements TitleBlockPluginInterface, ContainerFactoryPluginInterface, TrustedCallbackInterface {

  /**
   * The page title: a string (plain title) or a render array (formatted title).
   *
   * @var string|array
   */
  protected $title = '';

  /**
   * The route match.
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity repository service.
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RouteMatchInterface $route_match,
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'background' => TRUE,
      'description' => TRUE,
      'summary' => FALSE,
      'label_display' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['block_content'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Toggle elements'),
      '#description' => $this->t('Choose which elements you want to show in this block instance.'),
    ];
    $form['block_content']['background'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Background image'),
      '#description' => $this->t('Show image from entity "Background" (field_background) media field.'),
      '#default_value' => $this->configuration['background'],
    ];
    $form['block_content']['description'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Description (Taxonomy term)'),
      '#description' => $this->t('Show description of Taxonomy Term (or other entity which has the same field).'),
      '#default_value' => $this->configuration['description'],
    ];
    $form['block_content']['summary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Summary (Node)'),
      '#description' => $this->t('Show summary from Body field of Node (or other entity which has the same field).'),
      '#default_value' => $this->configuration['summary'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $block_branding = $form_state->getValue('block_content');
    $this->configuration['background'] = $block_branding['background'];
    $this->configuration['description'] = $block_branding['description'];
    $this->configuration['summary'] = $block_branding['summary'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $translation = NULL;
    $cacheable_metadata = new CacheableMetadata();
    if (preg_match('/^entity\.(.+)\.canonical$/', $this->routeMatch->getRouteName(), $matches)) {
      $entity = $this->routeMatch->getParameter($matches[1]);
      if ($entity) {
        $translation = $this->entityRepository->getTranslationFromContext($entity);
        $cacheable_metadata->addCacheableDependency($entity);
      }
    }

    if (
      $this->configuration['background'] &&
      $translation instanceof FieldableEntityInterface &&
      $translation->hasField('field_background') &&
      !$translation->get('field_background')->isEmpty()
    ) {
      /** @var \Drupal\Core\Entity\EntityInterface $background */
      $background = $translation->get('field_background')->entity;
      if ($background) {
        $build['#attributes']['class'][] = 'has-background';
        $build['background'] = $this->entityTypeManager
          ->getViewBuilder($background->getEntityTypeId())
          ->view($background);
        $build['background']['#attributes']['class'][] = 'background';
        $build['background']['#pre_render'][] = static::class . '::disableContextualLinks';
        unset($build['background']['#cache']['keys']);
        $cacheable_metadata->addCacheableDependency($background);
      }
    }

    $build['title'] = [
      '#type' => 'page_title',
      '#title' => $this->title,
    ];

    if (
      $this->configuration['description'] &&
      $translation instanceof FieldableEntityInterface &&
      $translation->hasField('description') &&
      !$translation->get('description')->isEmpty()
    ) {
      $build['content']['description'] = [
        '#type' => 'processed_text',
        '#text' => $translation->get('description')->value,
        '#format' => $translation->get('description')->format,
        '#langcode' => $translation->language()->getId(),
      ];
    }

    if (
      $this->configuration['summary'] &&
      $translation instanceof FieldableEntityInterface &&
      $translation->hasField('body') &&
      $translation->get('body')->summary
    ) {
      $build['content']['summary'] = [
        '#type' => 'processed_text',
        '#text' => $translation->get('body')->summary,
        '#format' => $translation->get('body')->format,
        '#langcode' => $translation->language()->getId(),
      ];
    }

    if (!empty($build['content'])) {
      $build['content'] += [
        '#type' => 'container',
        '#attributes' => ['class' => ['description']],
      ];
    }

    $build['#attributes']['class'][] = 'page-title-extended';
    $build['#attached']['library'][] = 'quanthub/page_title_block';

    $cacheable_metadata->applyTo($build);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['disableContextualLinks'];
  }

  /**
   * Disables Contextual Links for the embedded media by removing its property.
   *
   * @see \Drupal\Core\Entity\EntityViewBuilder::addContextualLinks()
   */
  public static function disableContextualLinks(array $build) {
    unset($build['#contextual_links']);
    return $build;
  }

}
