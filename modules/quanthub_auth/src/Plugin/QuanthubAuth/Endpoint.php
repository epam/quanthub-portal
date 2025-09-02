<?php

namespace Drupal\quanthub_auth\Plugin\QuanthubAuth;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\quanthub_auth\QuanthubAuthPluginInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides anonymous token from web endpoint.
 *
 * @QuanthubAuth(
 *   id = "endpoint",
 *   scope = \Drupal\quanthub_auth\QuanthubAuthPluginInterface::ANONYMOUS,
 *   label = @Translation("Anonymous token from endpoint"),
 *   priority = 0
 * )
 */
class Endpoint extends PluginBase implements QuanthubAuthPluginInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('cache.default'),
      $container->get('logger.channel.quanthub_auth'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected ClientInterface $httpClient,
    protected CacheBackendInterface $cache,
    protected LoggerInterface $logger,
  ) {
    if (!array_key_exists('url', $configuration)) {
      $configuration['url'] = $this->configFactory->get('quanthub_auth.plugins.endpoint')->get('url');
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getToken(): ?string {
    if (!$this->configuration['url']) {
      return NULL;
    }

    if ($cached = $this->cache->get('quanthub_auth:endpoint:token')) {
      return $cached->data;
    }

    if ($data = $this->retrieveToken($this->configuration['url'])) {
      $this->cache->set('quanthub_auth:endpoint:token', $data['token'], $data['expire'] - 120);
    }
    return $data['token'] ?? NULL;
  }

  /**
   * Helper to retrieve token.
   */
  protected function retrieveToken($url): ?array {
    try {
      $response = $this->httpClient->get($url, [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
      ]);

      $data = json_decode($response->getBody(), TRUE);
      return [
        'token' => $data['token'],
        'expire' => strtotime($data['expiresOn']),
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('Failed to retrieve tokens for anonymous user: @error.', [
        '@error' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): MarkupInterface|string {
    if (!$this->configuration['url']) {
      return $this->t('<strong>Inactive</strong>: endpoint URL is not configured.');
    }
    if (!$this->getToken()) {
      return $this->t('<strong>Error</strong>: see logs for details.');
    }
    return $this->t('<strong>Active</strong>');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('quanthub_auth.plugins.endpoint');

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Token endpoint URL'),
      '#default_value' => $config->get('url'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('quanthub_auth.plugins.endpoint');
    $config->set('url', $form_state->getValue('url'))->save();
  }

}
