<?php

namespace Drupal\quanthub_core\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\quanthub_core\PowerBIEmbedConfigs;
use GuzzleHttp\Psr7\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

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
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Create a PowerBIEmbedController instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('powerbi_embed_configs'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * PowerBIEmbedController constructor.
   *
   * @param \Drupal\quanthub_core\PowerBIEmbedConfigs $powerBIEmbedConfigs
   *   The PowerBIEmbedConfigs object.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   */
  public function __construct(PowerBIEmbedConfigs $powerBIEmbedConfigs, EntityTypeManager $entityTypeManager) {
    $this->powerBIEmbedConfigs = $powerBIEmbedConfigs;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Return Power BI embed configs in the json format.
   */
  public function postData($reportId, Request $request): JsonResponse | Response {
    if (!Uuid::isValid($reportId)) {
      return new Response(400);
    }

    $media_storage = $this->entityTypeManager->getStorage('media');
    $media_ids = $media_storage->getQuery()
      ->condition('bundle', 'power_bi')
      ->condition('field_media_power_bi.report_id', $reportId)
      ->execute();
    if (empty($media_ids)) {
      return new Response(400);
    }

    try {
      $content = json_decode($request->getContent(), TRUE, 3, JSON_THROW_ON_ERROR);
      return new JsonResponse($this->powerBIEmbedConfigs->getPowerEmbedConfig($reportId, $content['extraDatasets']));
    }
    catch (\Exception) {
      return new Response(400);
    }
  }

}
