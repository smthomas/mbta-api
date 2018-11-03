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
  public function request($endpoint, $params = [], $sort = FALSE) {
    try {
      $request_url = $this->baseURI . $endpoint;

      $response = $this->httpClient->get($request_url, [
        'headers' => $this->generateHeaders(),
        'query' => $this->generateQuery($params, $sort),
      ]);
      return $this->getData($response->getBody());
    }
    catch (RequestException $e) {
      watchdog_exception('mbta_api', $e, $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Generates query string for the API request.
   *
   * @return string
   *   Returns a string of API filters.
   */
  private function generateQuery($params, $sort) {
    $filters = [];
    if (!empty($params)) {
      $filters = $this->generateFilters($params);
    }

    // Add the sort to the query args array if a sort is provided.
    if ($sort) {
      $query_args = array_merge($filters, ['sort=' . $sort]);
    }
    else {
      $query_args = $filters;
    }

    return implode('&', $query_args);
  }

  /**
   * Generates filters for the API request.
   *
   * @return string
   *   Returns a string of API filters.
   */
  private function generateFilters($params) {
    $filters = [];

    foreach ($params as $key => $value) {
      $filters[] = 'filter[' . $key . ']=' . $value;
    }

    return $filters;
  }

  /**
   * Generates headers for the API request.
   *
   * @return array
   *   Returns an array containing headers for the API request.
   */
  private function generateHeaders() {
    return [
      'x-api-key' => $this->apiKey,
      'Accept-Encoding' => 'gzip',
    ];
  }

  /**
   * Extracts JSON data from the API response.
   *
   * @return array
   *   Returns an array of data or FALSE if no data is available.
   */
  private function getData($data) {
    if (empty($data)) {
      return FALSE;
    }

    $data_array = Json::decode($data);
    if (!isset($data_array['data'])) {
      return FALSE;
    }

    return $data_array['data'];
  }

}
