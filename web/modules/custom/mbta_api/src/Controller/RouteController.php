<?php

namespace Drupal\mbta_api\Controller;

use Drupal\mbta_api\MBTAClient;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
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
    $routes = $this->mbtaClient->request('/routes');

    if ($routes) {
      $route_rows = [];

      foreach ($routes as $route) {
        $attr = $route['attributes'];

        // Create table row styles and link to route schedule.
        $styles = [
          'background-color: #' . $attr['color'],
          'color: #' . $attr['text_color'],
        ];
        $link_url = Url::fromRoute('mbta_api.schedules_controller_content', [
          'route' => $route['id'],
        ]);

        // Create the table row for the route.
        $route_rows[] = [
          'style' => implode(';', $styles),
          'data' => [
            Link::fromTextAndUrl($attr['long_name'], $link_url),
            $this->t($attr['description']),
          ],
        ];
      }

      return [
        '#type' => 'table',
        '#header' => [
          $this->t('Route'),
          $this->t('Description'),
        ],
        '#rows' => $route_rows,
      ];
    }

    return [
      '#type' => 'markup',
      '#markup' => $this->t('No active Routes to display'),
    ];
  }

}
