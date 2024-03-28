<?php

namespace Drupal\quanthub_core\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for calendar ajax.
 */
class QuanthubCalendarController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new YourController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this controller.
    return new static(
    // Load the service required to construct this class.
      $container->get('database')
    );
  }

  /**
   * Get release events for calendar for one month.
   */
  public function ajaxUpdate(Request $request) {
    $queryArgs = $request->query->all();

    // Get query parameters from the request.
    $start = $request->query->get('start');
    $end = $request->query->get('end');

    $timezone = $request->query->get('timeZone');
    // @todo remove when postgres version will be 15>=.
    if ($timezone == 'Europe/Kyiv') {
      $timezone = 'Europe/Kiev';
    }

    $start_date = new DrupalDateTime($start, new \DateTimeZone($timezone));
    $end_date = new DrupalDateTime($end, new \DateTimeZone($timezone));

    $interval = $end_date->diff($start_date);

    if ($interval->m < 2) {
      // Format the dates (optional).
      $start_formatted = $start_date->getTimestamp();
      $end_formatted = $end_date->getTimestamp();

      $query = $this->database
        ->select('node_field_data', 'n')
        ->fields('n', ['title']);

      $query->join('node__field_release_date', 'nfrd', 'nfrd.entity_id = n.nid');
      $query->join('node__field_rich_brief_descr', 'nrbd', 'nrbd.entity_id = n.nid');
      $query->join('node__field_release_type', 'nfrt', 'nfrt.entity_id = n.nid');

      $query->condition('n.type', 'release');
      $query->condition('nfrd.field_release_date_value', $start_formatted, '>');
      $query->condition('nfrd.field_release_date_end_value', $end_formatted, '<');

      $query->addField('n', 'nid', 'eid');
      $query->addField('n', 'nid', 'id');
      $query->addField('nrbd', 'field_rich_brief_descr_value', 'des');
      $query->addExpression("TO_CHAR(to_timestamp(nfrd.field_release_date_value) AT TIME ZONE :timezone, 'YYYY-MM-DD\"T\"HH24:MI:SS')", 'start', [':timezone' => $timezone]);
      $query->addExpression("TO_CHAR(to_timestamp(nfrd.field_release_date_end_value) AT TIME ZONE :timezone, 'YYYY-MM-DD\"T\"HH24:MI:SS')", 'end', [':timezone' => $timezone]);
      $query->addExpression("FALSE", 'eventDurationEditable');
      $query->addExpression("CONCAT('/node/', n.nid)", 'url');
      $query->addExpression("CASE nfrt.field_release_type_value
        WHEN 'dataset' THEN '#0B8043'
        WHEN 'press_release' THEN '#3F51B5'
        WHEN 'report_submission' THEN '#CC2E4F'
        WHEN 'other' THEN '#616161'
        ELSE '#616161' END",
        'backgroundColor'
      );
      $query->addExpression("CASE WHEN
        TO_CHAR(to_timestamp(nfrd.field_release_date_value) AT TIME ZONE :timezone, 'HH24:MI') = '00:00'
        THEN 1
        ELSE 0 END",
        'allDay',
        [':timezone' => $timezone]
      );
      // @todo need to add alias.
      $query->range(0, 10);
      $data = $query->execute()->fetchAll();

      // Fullcalendar.js need this value as bool.
      foreach ($data as $key => $value) {
        if ($data[$key]->allDay == FALSE) {
          $data[$key]->allDay = FALSE;
        }
        else {
          $data[$key]->allDay = TRUE;
        }
      }

      $response = new CacheableJsonResponse($data);

      // Create a CacheableMetadata object to hold cacheability metadata.
      $cacheableMetadata = new CacheableMetadata();

      if (isset($queryArgs['start'])) {
        $cacheableMetadata->addCacheContexts([
          'url.query_args:start',
          'url.query_args:end',
          'url.query_args:timeZone',
        ]);
        $cacheableMetadata->addCacheTags(['node_list:release']);
        $response->addCacheableDependency($cacheableMetadata);
      }

    }
    else {
      $response = new JsonResponse([]);
    }

    return $response;
  }

}
