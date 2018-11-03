<?php

namespace Drupal\mbta_api;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Component\Serialization\Json;

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
   * String containing the MBTA API Key.
   *
   * @var string
   */
  protected $apiKey;

  /**
   * String containing the base URI for API requests.
   *
   * @var string
   */
  protected $baseURI = 'https://api-v3.mbta.com';

  /**
   * Constructs a new MBTAClient object.
   */
  public function __construct(ClientInterface $http_client, $config) {
    $this->httpClient = $http_client;
    $this->apiKey = $config->get('mbta_api.mbtaadmin')->get('mbta_api_key');
  }

  /**
   * Make an API request to the MBTA api.
   */
  public function request($endpoint) {
    try {
      $response = $this->httpClient->get($this->baseURI . $endpoint, [
        'headers' => ['x-api-key' => $this->apiKey],
      ]);
      $data = $response->getBody();

      if (empty($data)) {
        return FALSE;
      }

      $data_array = Json::decode($data);

      if (!isset($data_array['data'])) {
        return FALSE;
      }

      return $data_array['data'];
    }
    catch (RequestException $e) {
      watchdog_exception('mbta_api', $e, $e->getMessage());
      return FALSE;
    }
  }

}
