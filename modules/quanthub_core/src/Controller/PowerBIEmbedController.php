<?php

namespace Drupal\quanthub_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\quanthub_core\PowerBIEmbedConfigs;
use Laminas\Diactoros\Response\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Return Power BI embed config.
 */
class PowerBIEmbedController extends ControllerBase {

  /**
   * The PowerBIEmbedConfigs object.
   *
   * @var \Drupal\quanthub_core\PowerBIEmbedConfigs
   */
  protected $powerBIEmbedConfigs;

  /**
   * Create a PowerBIEmbedController instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('powerbi_embed_configs')
    );
  }

  /**
   * PowerBIEmbedController constructor.
   *
   * @param \Drupal\quanthub_core\PowerBIEmbedConfigs $powerBIEmbedConfigs
   *   The PowerBIEmbedConfigs object.
   */
  public function __construct(PowerBIEmbedConfigs $powerBIEmbedConfigs) {
    $this->powerBIEmbedConfigs = $powerBIEmbedConfigs;
  }

  /**
   * Return Power BI embed configs in the json format.
   */
  public function postData($reportId, Request $request): JsonResponse {
    $content = json_decode($request->getContent(), TRUE);
    return new JsonResponse($this->powerBIEmbedConfigs->getPowerEmbedConfig($reportId, $content["extraDatasets"]));
  }

}
