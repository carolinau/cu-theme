<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client_request_test\Service;

use Drupal\entity_share_client\Service\RequestService;
use GuzzleHttp\Client;

/**
 * Class TestRequestService.
 *
 * @package Drupal\entity_share_client_request_test\Service
 */
class TestRequestService extends RequestService {

  /**
   * A mapping, URL => response, from the GET requests made.
   *
   * @var \Psr\Http\Message\ResponseInterface[]
   */
  protected $responseMapping = [];

  /**
   * {@inheritdoc}
   */
  public function request(Client $http_client, $method, $url) {
    // It it is a GET request store the result to be able to re-obtain the
    // result to simulate another website.
    if ($method == 'GET') {
      if (!isset($this->responseMapping[$url])) {
        $this->responseMapping[$url] = parent::request($http_client, $method, $url);
      }

      return $this->responseMapping[$url];
    }

    return parent::request($http_client, $method, $url);
  }

}
