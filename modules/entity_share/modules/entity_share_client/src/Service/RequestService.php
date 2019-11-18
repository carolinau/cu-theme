<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use GuzzleHttp\Client;

/**
 * Class RequestService.
 *
 * @package Drupal\entity_share_client\Service
 */
class RequestService implements RequestServiceInterface {

  /**
   * {@inheritdoc}
   */
  public function request(Client $http_client, $method, $url) {
    return $http_client->request($method, $url);
  }

}
