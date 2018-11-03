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
   * Tracks all the available train stops in an array keyed by the id.
   *
   * @var array
   */
  private $allStops;

  /**
   * Constructs a new SchedulesController object.
   */
  public function __construct(MBTAClient $mbtaClient) {
    $this->mbtaClient = $mbtaClient;
    $this->tripNames = [];
    $this->allStops = [];
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
    $trips = $this->mbtaClient->request('/trips', $params, 'id');

    // Get all stops for this route and direction.
    $stops = $this->mbtaClient->request('/stops', $params);

    // Get all schedules to fill in the time on the schedule matrix.
    $schedules = $this->mbtaClient->request('/schedules', $params);

    // Get all the predictions to update any scheduled items that have been delayed.
    $predictions = $this->mbtaClient->request('/predictions', $params);

    if ($stops && $trips && $schedules) {
      // Generate the data structure.
      $this->generateScheduleData($trips, $stops, $route);

      // Update data with the original schedule and any realtime predictions.
      $this->updateScheduleMatrix($schedules);
      $this->updateScheduleMatrix($predictions);

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
        ],
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
  private function generateScheduleData($trips, $stops, $route) {
    $trips_array = [];

    // Build the list of trip names and trip ids.
    foreach ($trips as $trip) {
      // Verify this trip is part of the correct route.
      // This is needed because the API can return back Shuttle routes
      // that are not actually part of the train routes.
      if ($trip['relationships']['route']['data']['id'] == $route) {
        if (empty($trip['attributes']['name'])) {
          $this->tripNames[] = $trip['id'];
        }
        else {
          $this->tripNames[] = $trip['attributes']['name'];
        }

        $trips_array[$trip['id']] = '';
      }
    }

    // Loop through the available stops and build out matrix structure.
    foreach ($stops as $stop) {
      $this->scheduleMatrix[$stop['attributes']['name']] = $trips_array;
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

  /**
   * Loops through an schedule array and updates times in the schedule matrix.
   */
  private function updateScheduleMatrix($schedule_items) {
    foreach ($schedule_items as $schedule) {
      // If this is a first or last stop the departure/arrival time could be null.
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
   * Gets all the available stops as a keyed array.
   */
  private function getAllStops() {
    $stops = $this->mbtaClient->request('/stops', ['route_type' => '0,1,2']);

    foreach ($stops as $stop) {
      $this->allStops[$stop['id']] = $stop;
    }
  }

}
