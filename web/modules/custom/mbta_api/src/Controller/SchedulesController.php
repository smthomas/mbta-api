<?php

namespace Drupal\mbta_api\Controller;

use Drupal\mbta_api\MBTAClient;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SchedulesController.
 */
class SchedulesController extends ControllerBase {

  /**
   * MBTA Client for accessing the MBTA API.
   *
   * @var Drupal\mbta_api\MBTAClientMBTAClient
   */
  private $mbtaClient;

  /**
   * Keeps track of the available trip names.
   *
   * @var array
   */
  private $tripNames;

  /**
   * The schedule matrix used to output schedule data.
   *
   * @var array
   */
  private $scheduleMatrix;

  /**
   * Constructs a new SchedulesController object.
   */
  public function __construct(MBTAClient $mbtaClient) {
    $this->mbtaClient = $mbtaClient;
    $this->tripNames = [];
  }

  /**
   * Creates a new RouteController object with the api client.
   *
   * @param Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return SchedulesController
   *   Returns the new SchedulesController object.
   */
  public static function create(ContainerInterface $container) {
    $mbtaClient = $container->get('mbta_api.client');
    return new static($mbtaClient);
  }

  /**
   * Outputs MBTA Route Schedule in a table format.
   *
   * @return array
   *   Returns render array with MBTA route schedule in a table format.
   */
  public function content($route, $direction) {
    $params = [
      'route' => $route,
      'direction_id' => $direction,
      'date' => date('Y-m-d'),
    ];

    // Get all trips for this route and direction .
    $trips = $this->mbtaClient->request('/trips', $params, 'name');

    // Get all stops for this route and direction.
    $stops = $this->mbtaClient->request('/stops', $params);

    // Get all schedules and fill in the time on the schedule matrix.
    $schedules = $this->mbtaClient->request('/schedules', $params);

    // @todo Allow changing direction (with a link).
    // @todo Utilize the predictions.
    // @todo Output the table.

    if ($stops && $trips && $schedules) {
      $this->generateScheduleData($trips, $stops, $schedules);

      $rows = [];
      foreach ($this->scheduleMatrix as $stop_name => $stop_row) {
        $rows[] = array_merge([$stop_name], $stop_row);
      }

      return [
        'link' => $this->generateTripDirectionLink($route, $direction),
        'table' => [
          '#type' => 'table',
          '#header' => array_merge([$this->t('Stop')], $this->tripNames),
          '#rows' => $rows,
        ]
      ];
    }

    return [
      '#type' => 'markup',
      '#markup' => $this->t('No active Schedule to display'),
    ];
  }

  /**
   * Build multidimensional schedule matrix to use to display schedule data.
   */
  private function generateScheduleData($trips, $stops, $schedules) {
    $trips_array = [];

    // Build the list of trip names and trip ids.
    foreach ($trips as $trip) {
      $this->tripNames[] = $trip['attributes']['name'];
      $trips_array[$trip['id']] = '';
    }

    // Loop through the available stops and build out matrix structure.
    foreach ($stops as $stop) {
      $this->scheduleMatrix[$stop['attributes']['name']] = $trips_array;
    }

    // Go through the schedule and add departure times to the matrix.
    foreach ($schedules as $schedule) {
      $date = new \DateTime($schedule['attributes']['departure_time']);
      $time = $date->format('h:i A');
      $stop = $schedule['relationships']['stop']['data']['id'];
      $trip = $schedule['relationships']['trip']['data']['id'];

      $this->scheduleMatrix[$stop][$trip] = $time;
    }
  }

  /**
   * Builds link to reverse the direction of the schedule.
   */
  private function generateTripDirectionLink($route, $direction) {
    $stops = array_keys($this->scheduleMatrix);
    $link_text = $stops[0];
    $link_text .= ' -> ';
    $link_text .= $stops[count($stops) - 1];

    $link_url = Url::fromRoute('mbta_api.schedules_controller_content', [
      'route' => $route,
      'direction' => $direction === 0 ? 1 : 0,
    ]);

    return [
      '#title' => $link_text,
      '#type' => 'link',
      '#url' => $link_url,
    ];
  }

}
