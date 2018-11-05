<?php

namespace Drupal\mbta_api\Controller;

use Drupal\mbta_api\MBTAClient;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RouteController.
 */
class RouteController extends ControllerBase {

  /**
   * MBTA Client for accessing the MBTA API.
   *
   * @var mbtaClient
   */
  private $mbtaClient;

  /**
   * Constructs a new RouteController object.
   *
   * @param Drupal\mbta_api\MBTAClient $mbtaClient
   *   The service for accessing the MBTA API.
   */
  public function __construct(MBTAClient $mbtaClient) {
    $this->mbtaClient = $mbtaClient;
  }

  /**
   * Creates a new RouteController object with the api client.
   *
   * @param Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return RouteController
   *   Returns the new RouteController object.
   */
  public static function create(ContainerInterface $container) {
    $mbtaClient = $container->get('mbta_api.client');
    return new static($mbtaClient);
  }

  /**
   * Outputs MBTA Routes in a table format.
   *
   * @return array
   *   Returns render array with MBTA routes in a table format.
   */
  public function content() {
    $params = [
      'type' => '0,1,2',
    ];
    $routes = $this->mbtaClient->request('routes', $params);

    if ($routes) {
      $route_rows = [];

      foreach ($routes as $route) {
        $attr = $route['attributes'];

        // Create the table row for the route.
        $params = [
          'route' => $route['id'],
        ];
        $options = [
          'attributes' => ['style' => 'color: #' . $attr['text_color']],
        ];

        $route_rows[$attr['description']][] = [
          'style' => 'background-color: #' . $attr['color'],
          'data' => [
            Link::createFromRoute($attr['long_name'], 'mbta_api.schedules_controller_content', $params, $options),
          ],
        ];

      }

      $render_array = [];
      foreach ($route_rows as $group => $rows) {
        $render_array[] = [
          '#type' => 'markup',
          '#markup' => '<h3>' . $group . '</h3>',
        ];
        $render_array[] = [
          '#type' => 'table',
          '#rows' => $rows,
        ];
      }

      // Add caching for max-age of 10 minutes.
      $render_array['#cache'] = [
        'max-age' => 600,
      ];

      return $render_array;
    }

    return [
      '#type' => 'markup',
      '#markup' => $this->t('No active Routes to display'),
      '#cache' => [
        'max-age' => 600,
      ],
    ];
  }

}
