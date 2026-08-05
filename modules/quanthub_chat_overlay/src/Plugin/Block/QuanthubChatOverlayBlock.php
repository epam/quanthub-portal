<?php

namespace Drupal\quanthub_chat_overlay\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an AI dial chat overlay block.
 *
 * @Block(
 *   id = "quanthub_chat_overlay_block",
 *   admin_label = @Translation("AI Dial Chat Overlay"),
 *   category = @Translation("Custom"),
 * )
 */
class QuanthubChatOverlayBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * A config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $configFactory,
    AccountInterface $account,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $configFactory->get('quanthub_chat_overlay.settings');
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build['content'] = [
      '#theme' => 'quanthub_chat_overlay',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResult {
    $result = AccessResult::allowedIf($this->config->get('api') === 'v2' && $this->config->get('chat_url'))
      ->addCacheableDependency($this->config);

    return AccessResult::allowedIfHasPermission($this->account, 'access ai')
      ->andIf($result);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(
      parent::getCacheTags(),
      $this->config->getCacheTags()
    );
  }

}
