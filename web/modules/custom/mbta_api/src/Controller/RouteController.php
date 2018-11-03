<?php

namespace Drupal\mbta_api\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class RouteController.
 */
class RouteController extends ControllerBase {

  /**
   * Outputs MBTA Routes in a table format.
   *
   * @return array
   *   Returns render array with MBTA routes in a table format.
   */
  public function content() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Implement method: content')
    ];
  }

}
