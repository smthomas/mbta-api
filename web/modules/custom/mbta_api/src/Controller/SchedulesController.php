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
   * @var Drupal\mbta_api\MBTAClient
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
   * Tracks all the available train stops in an array keyed by the id.
   *
   * @var array
   */
  private $allStops;

  /**
   * Constructs a new SchedulesController object.
   *
   * @param Drupal\mbta_api\MBTAClient $mbtaClient
   *   The service for accessing the MBTA API.
   */
  public function __construct(MBTAClient $mbtaClient) {
    $this->mbtaClient = $mbtaClient;
    $this->tripNames = [];
    $this->allStops = [];

    // Initialize the schedule matrix with the first row as the header.
    $this->scheduleMatrix['header'] = [];
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
   * @param string $route
   *   The name of the route.
   * @param int $direction
   *   The direction of the route (0 or 1).
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
    $trips = $this->mbtaClient->request('trips', $params, 'id');

    // Get all stops for this route and direction.
    $stops = $this->mbtaClient->request('stops', $params);

    // Get all schedules to fill in the time on the schedule matrix.
    $schedules = $this->mbtaClient->request('schedules', $params, 'departure_time');

    // Get all the predictions to update any real-time changes to the schedule.
    // We don't need to sort this request and we don't want it to be cacheable.
    $predictions = $this->mbtaClient->request('predictions', $params, FALSE, FALSE);

    if ($stops && $trips && $schedules) {
      // Generate the schedule matrix data structure.
      $this->generateScheduleData($trips, $stops, $schedules, $route);

      // Update data with the original schedule and any realtime predictions.
      $this->updateScheduleMatrix($schedules);
      $this->updateScheduleMatrix($predictions);

      // Pull the header from the first row of the matrix.
      $header = array_merge([$this->t('Stop')], array_shift($this->scheduleMatrix));

      // Build out the rows for the table.
      $rows = [];
      foreach ($this->scheduleMatrix as $stop_name => $stop_row) {
        $rows[] = array_merge([$stop_name], $stop_row);
      }

      return [
        'link' => $this->generateTripDirectionLink($route, $direction),
        'table' => [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows,
        ],
        '#cache' => [
          'max-age' => 60,
        ],
      ];
    }

    return [
      '#type' => 'markup',
      '#markup' => $this->t('No active Schedule to display'),
      '#cache' => [
        'max-age' => 60,
      ],
    ];
  }

  /**
   * Build multidimensional schedule matrix to use to display schedule data.
   *
   * This builds out the entire table structure for the schedule.
   *
   * @param array $trips
   *   The available trips on this route.
   * @param array $stops
   *   All the stops on this route.
   * @param array $schedules
   *   The schedule array for this route.
   * @param string $route
   *   The name of the route.
   */
  private function generateScheduleData(array $trips, array $stops, array $schedules, $route) {
    // Loop through the available trips and build a trip names lookup array.
    foreach ($trips as $trip) {
      if (!empty($trip['attributes']['name'])) {
        $this->tripNames[$trip['id']] = $trip['attributes']['name'];
      }
      else {
        $this->tripNames[$trip['id']] = $trip['id'];
      }
    }

    // Loop through the entire schedule to generate the correct trip columns.
    $trip_array = [];
    foreach ($schedules as $schedule) {
      // Verify this trip is part of the correct route.
      // This is needed because the API can return back Shuttle routes
      // that are not actually part of the train routes.
      if ($schedule['relationships']['route']['data']['id'] == $route) {
        $trip = $schedule['relationships']['trip']['data']['id'];
        if (!isset($trip_array[$trip])) {
          $trip_array[$trip] = '';
          $this->scheduleMatrix['header'][] = $this->tripNames[$trip];
        }
      }
    }

    // Loop through the available stops and build out matrix structure.
    foreach ($stops as $stop) {
      $this->scheduleMatrix[$stop['attributes']['name']] = $trip_array;
    }
  }

  /**
   * Builds link to reverse the direction of the schedule.
   *
   * @param string $route
   *   The name of the route.
   * @param int $direction
   *   The direction of the route (0 or 1).
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

  /**
   * Loops through an schedule array and updates times in the schedule matrix.
   *
   * @param array $schedule_items
   *   An array of scheduled items for building out the schedule matrix.
   */
  private function updateScheduleMatrix(array $schedule_items) {
    foreach ($schedule_items as $schedule) {
      // If this is a first/last stop the departure/arrival time could be null.
      // We make sure we grab a valid time.
      if ($schedule['attributes']['departure_time']) {
        $date = new \DateTime($schedule['attributes']['departure_time']);
      }
      else {
        $date = new \DateTime($schedule['attributes']['arrival_time']);
      }

      $time = $date->format('h:i A');
      $stop = $schedule['relationships']['stop']['data']['id'];
      $trip = $schedule['relationships']['trip']['data']['id'];

      // If this stop and trip are not available, this means the stop has a
      // parent station that needs to be looked up.
      if (!isset($this->scheduleMatrix[$stop][$trip])) {
        // If we haven't made an API call to get all the avaialble train stops,
        // we do that now.
        if (empty($this->allStops)) {
          $this->getAllStops();
        }

        // If this stop has a parent station, use that instead.
        if (isset($this->allStops[$stop]['attributes']['name'])) {
          $stop = $this->allStops[$stop]['attributes']['name'];
        }
      }

      // Set the time on the schedule matrix.
      if (isset($this->scheduleMatrix[$stop][$trip])) {
        $this->scheduleMatrix[$stop][$trip] = $time;
      }
    }
  }

  /**
   * Call the MBTA API to get  all the available stops as a keyed array.
   */
  private function getAllStops() {
    $stops = $this->mbtaClient->request('stops', ['route_type' => '0,1,2']);

    foreach ($stops as $stop) {
      $this->allStops[$stop['id']] = $stop;
    }
  }

}
