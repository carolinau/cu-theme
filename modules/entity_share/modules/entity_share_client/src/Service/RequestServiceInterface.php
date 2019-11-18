<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use GuzzleHttp\Client;

/**
 * Request service interface methods.
 */
interface RequestServiceInterface {

  /**
   * Performs a HTTP request. Wraps the Guzzle HTTP client.
   *
   * Why wrap the Guzzle HTTP client? Because we want to be able to override
   * this service during tests to emulate another website.
   *
   * @param \GuzzleHttp\Client $http_client
   *   The HTTP client.
   * @param string $method
   *   HTTP method.
   * @param string $url
   *   URL to request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  public function request(Client $http_client, $method, $url);

}
