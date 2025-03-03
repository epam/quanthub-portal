<?php

namespace Drupal\quanthub_core\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\quanthub_core\PowerBIEmbedConfigs;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Power BI API controller.
 */
class PowerBiApiController extends ControllerBase {

  /**
   * The http foundation factory service.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface
   */
  protected HttpFoundationFactoryInterface $foundationFactory;

  /**
   * The http client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The Power BI Embed configs.
   *
   * @var \Drupal\quanthub_core\PowerBIEmbedConfigs
   */
  protected $powerBIEmbedConfigs;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * PowerBIDownloadController constructor.
   *
   * @param \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface $foundation_factory
   *   The http foundation factory service.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The http client service.
   * @param \Drupal\quanthub_core\PowerBIEmbedConfigs $powerBIEmbedConfigs
   *   The Power BI Embed configs.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    HttpFoundationFactoryInterface $foundation_factory,
    ClientInterface $httpClient,
    PowerBIEmbedConfigs $powerBIEmbedConfigs,
    ConfigFactory $configFactory,
    LoggerInterface $logger,
  ) {
    $this->foundationFactory = $foundation_factory;
    $this->httpClient = $httpClient;
    $this->powerBIEmbedConfigs = $powerBIEmbedConfigs;
    $this->configFactory = $configFactory;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('psr7.http_foundation_factory'),
      $container->get('http_client'),
      $container->get('powerbi_embed_configs'),
      $container->get('config.factory'),
      $container->get('logger.factory')->get('powerbi_download')
    );
  }

  /**
   * Get the PowerBI access token.
   *
   * @return string
   *   The token.
   */
  protected function getToken() {
    return $this->powerBIEmbedConfigs->getPowerBiAccessToken();
  }

  /**
   * Get the group ID.
   *
   * @return string
   *   The group ID.
   */
  protected function getGroupId() {
    $config = $this->configFactory->get('powerbi_embed.settings');
    return $config->get('workspace_id');
  }

  /**
   * Handle the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $reportId
   *   The Power BI report ID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function proxyPowerBIRequest(Request $request, string $reportId): Response {
    $headers = [];
    foreach ($request->headers->keys() as $key) {
      if (($key == 'content-type') || str_starts_with($key, 'accept')) {
        $headers[$key] = $request->headers->get($key);
      }
    }
    $headers['authorization'] = 'Bearer ' . $this->getToken();

    $options = [
      'headers' => $headers,
      'allow_redirects' => FALSE,
    ];

    if ($body = $request->getContent()) {
      $options['body'] = $body;
    }

    try {
      $groupId = $this->getGroupId();
      $baseApiUrl = "https://api.powerbi.com/v1.0/myorg/groups/$groupId/reports/$reportId";
      $uri = $request->getRequestUri();
      $uriParts = explode($reportId, $uri);
      $response = $this->httpClient->request(
        $request->getMethod(), $baseApiUrl . end($uriParts), $options
      );

      return $this->foundationFactory->createResponse($response);
    }
    catch (RequestException $exception) {
      $response = $exception->getResponse();
      if (!is_null($response) && !is_null($response->getBody())) {
        $response_info = $response->getBody()->getContents();
        $message = new FormattableMarkup(
          'API connection error. Error details are as follows:<pre>@response</pre>', [
            '@response' => print_r(json_decode($response_info), TRUE),
          ]);
        $this->logger->error($message);

        return $this->foundationFactory->createResponse($exception->getResponse());
      }
      else {
        $this->logger->warning($exception->getMessage());
        throw $exception;
      }
    }
  }

}
