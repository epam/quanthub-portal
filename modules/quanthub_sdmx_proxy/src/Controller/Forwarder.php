<?php

namespace Drupal\quanthub_sdmx_proxy\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\quanthub_core\UserInfo;

/**
 * Main controller to forward requests.
 */
final class Forwarder extends ControllerBase {

  /**
   * The user info service.
   *
   * @var \Drupal\quanthub_core\UserInfo
   */
  protected $userInfo;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private $client;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Translates between Symfony and PRS objects.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface
   */
  private $foundationFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('psr7.http_foundation_factory'),
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('user_info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ClientInterface $client,
    HttpFoundationFactoryInterface $foundation_factory,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactory $config_factory,
    UserInfo $user_info
  ) {
    $this->client = $client;
    $this->foundationFactory = $foundation_factory;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->userInfo = $user_info;
  }

  /**
   * Forwards incoming requests to the connected API.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function forward(Request $request): Response {
    $config = $this->configFactory->get('quanthub_sdmx_proxy.settings');

    $headers = [];
    foreach ($request->headers->keys() as $key) {
      if (($key == 'content-type') || ($key == 'accept') && ($key == 'accept-language') && ($key == 'accept-encoding')) {
        $headers[$key] = $request->headers->get($key);
      }
    }
    $headers['authorization'] = 'Bearer ' . $this->userInfo->getToken();

    $options = [
      'headers' => $headers,
    ];
    if ($body = $request->getContent()) {
      $options['body'] = $body;
    }

    try {
      $uri = $request->query->get('uri');
      $psr7_response = $this->client->request($request->getMethod(), ($config->get('api_url')) . $uri, $options);
      return $this->foundationFactory->createResponse($psr7_response);
    }
    catch (GuzzleException $exception) {
      // Get the original response.
      $response = $exception->getResponse();
      if (!is_null($response->getBody())) {
        // Get the info returned from the remote server.
        $response->getBody()->getContents();
        // Using FormattableMarkup allows for the use of <pre/> tags,
        // giving a more readable log item.
        $message = new FormattableMarkup('API connection error. Error details are as follows:<pre>@response</pre>', ['@response' => print_r(json_decode($response_info), TRUE)]);
        // Log the error.
        $this->loggerFactory->get('quanthub_sdmx_proxy')->warning($message);
      }
      return $this->foundationFactory->createResponse($exception->getResponse());
    }
    catch (ClientException $exception) {
      return $this->foundationFactory->createResponse($exception->getResponse());
    }
    catch (ServerException $exception) {
      return $this->foundationFactory->createResponse($exception->getResponse());
    }
  }

}
