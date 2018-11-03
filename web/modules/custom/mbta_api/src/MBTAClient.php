<?php

namespace Drupal\mbta_api;
use GuzzleHttp\ClientInterface;

/**
 * Class MBTAClient.
 */
class MBTAClient {

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;
  /**
   * Constructs a new MBTAClient object.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

}
