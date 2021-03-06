<?php

namespace Drupal\mbta_api;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;

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
   * Caching service to cache API responses.
   *
   * @var Drupal\Core\Cache\CacheBackendInterface
   */
  private $cache;

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
  protected $baseURI = 'https://api-v3.mbta.com/';

  /**
   * Constructs a new MBTAClient object.
   */
  public function __construct(ClientInterface $http_client, $config, CacheBackendInterface $cache) {
    $this->httpClient = $http_client;
    $this->apiKey = $config->get('mbta_api.mbtaadmin')->get('mbta_api_key');
    $this->cache = $cache;
  }

  /**
   * Make an API request to the MBTA api.
   */
  public function request($endpoint, $params = [], $sort = FALSE, $cacheable = TRUE) {
    try {
      $data = FALSE;
      if ($cacheable) {
        $cache_key = $endpoint . '_' . implode('_', $params);
        $cache_results = $this->cache->get($cache_key);
        $data = isset($cache_results->data) ? $cache_results->data : FALSE;
      }

      if (!isset($data) || empty($data)) {
        $request_url = $this->baseURI . $endpoint;

        $response = $this->httpClient->get($request_url, [
          'headers' => $this->generateHeaders(),
          'query' => $this->generateQuery($params, $sort),
        ]);

        $data = $this->getData($response->getBody());

        if ($cacheable) {
          // Store this response in the cache and set a 10 minute expiration.
          $this->cache->set($cache_key, $data, time() + 600, ['mbta_api']);
        }
      }

      return $data;
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
    $query = [];
    // Add filters to the query if filters are provided.
    if (!empty($params)) {
      $query['filter'] = $params;
    }

    // Add the sort to the query if a sort is provided.
    if ($sort) {
      $query['sort'] = $sort;
    }

    return http_build_query($query);
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
