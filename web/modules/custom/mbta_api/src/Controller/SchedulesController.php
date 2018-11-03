<?php

namespace Drupal\mbta_api\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class SchedulesController.
 */
class SchedulesController extends ControllerBase {

  /**
   * Content.
   *
   * @return string
   *   Return Hello string.
   */
  public function content($route) {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Implement method: content with parameter(s): $route'),
    ];
  }

}
