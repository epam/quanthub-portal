<?php

namespace Drupal\quanthub_sdmx_proxy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Utility\Error;
use Drupal\quanthub_auth\QuanthubAuthInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Main controller to forward requests.
 */
final class Forwarder extends ControllerBase {

  /**
   * SDMX API configuration.
   */
  protected array $config;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('logger.channel.quanthub_sdmx_proxy'),
      $container->get('quanthub.auth', ContainerInterface::NULL_ON_INVALID_REFERENCE),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerInterface $logger,
    protected ?QuanthubAuthInterface $auth,
  ) {
    $this->config = $this->config('quanthub.settings')->get('third_party_settings.quanthub_sdmx') ?: [];
  }

  /**
   * Forwards incoming requests to the connected API.
   */
  public function forward(Request $request) {
    return $this->forwardInternal($request, $this->config['url'] ?? '');
  }

  /**
   * Forwards incoming requests to the connected Download API.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function forwardDownload(Request $request) {
    $url = $this->config['url'] ?? '';
    // Download goes through v2 endpoint.
    return $this->forwardInternal($request, str_replace('/v1', '/v2', $url));
  }

  /**
   * Forwards incoming requests to the connected API.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   * @param string $url
   *   API URL.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  protected function forwardInternal(Request $request, $url) {
    if (!$url) {
      throw new NotFoundHttpException('SDMX API Base URL is not configured.');
    }
    if (!($uri = $request->query->get('uri'))) {
      throw new NotFoundHttpException();
    }

    $headers = [];
    foreach ($request->headers->keys() as $key) {
      if (($key == 'content-type') || str_starts_with($key, 'accept') || str_starts_with($key, 'quanthub')) {
        $headers[$key] = $request->headers->get($key);
      }
    }
    if ($this->auth && ($token = $this->auth->getUserToken())) {
      $headers['authorization'] = "Bearer $token";
    }

    $options = [
      'headers' => $headers,
      'http_errors' => FALSE,
    ];
    if ($body = $request->getContent()) {
      $options['body'] = $body;
    }

    try {
      return $this->httpClient->request($request->getMethod(), $url . $uri, $options)
        ->withAddedHeader('X-Proxy-Status-Code', 200);
    }
    catch (GuzzleException $e) {
      Error::logException($this->logger, $e);
      // Keep real SDMX API host in secret.
      if ($host = parse_url($url, PHP_URL_HOST)) {
        $details = str_replace($host, '******', $e->getMessage());
      }
      $content = [
        'message' => 'SDMX API connection error, check the logs for more information.',
        'details' => $details ?? $e->getMessage(),
      ];
      return new JsonResponse($content, 503, ['X-Proxy-Status-Code' => 503]);
    }
  }

}
