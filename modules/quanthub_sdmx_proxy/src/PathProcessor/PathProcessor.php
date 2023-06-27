<?php

namespace Drupal\quanthub_sdmx_proxy\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a path processor to rewrite file URLs.
 *
 * As the route system does not allow arbitrary amount of parameters convert
 * the path to a query parameter on the request.
 */
class PathProcessor implements InboundPathProcessorInterface {

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritDoc}
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    if (strpos($path, '/sdmx/') === 0) {
      $requestUri = $this->requestStack->getCurrentRequest()->getRequestUri();
      $uri = preg_replace('|^\/sdmx|', '', $requestUri);
      $request->query->set('uri', $uri);
      return '/sdmx';
    }

    return $path;
  }

}
