<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Http\ClientFactory;
use Drupal\entity_share_client\Entity\RemoteInterface;

/**
 * Class RemoteManager.
 *
 * @package Drupal\entity_share_client\Service
 */
class RemoteManager implements RemoteManagerInterface {

  /**
   * The HTTP client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * The request service.
   *
   * @var \Drupal\entity_share_client\Service\RequestServiceInterface
   */
  protected $requestService;

  /**
   * RemoteManager constructor.
   *
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   *   The HTTP client factory.
   * @param \Drupal\entity_share_client\Service\RequestServiceInterface $request_service
   *   The request service.
   */
  public function __construct(
    ClientFactory $http_client_factory,
    RequestServiceInterface $request_service
  ) {
    $this->httpClientFactory = $http_client_factory;
    $this->requestService = $request_service;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareClient(RemoteInterface $remote) {
    $http_client = $this->httpClientFactory->fromOptions([
      'base_uri' => $remote->get('url') . '/',
      'cookies' => TRUE,
      'allow_redirects' => TRUE,
    ]);

    $http_client->post('user/login', [
      'form_params' => [
        'name' => $remote->get('basic_auth_username'),
        'pass' => $remote->get('basic_auth_password'),
        'form_id' => 'user_login_form',
      ],
    ]);

    return $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareJsonApiClient(RemoteInterface $remote) {
    return $this->httpClientFactory->fromOptions([
      'base_uri' => $remote->get('url') . '/',
      'auth' => [
        $remote->get('basic_auth_username'),
        $remote->get('basic_auth_password'),
      ],
      'headers' => [
        'Content-type' => 'application/vnd.api+json',
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getChannelsInfos(RemoteInterface $remote) {
    $http_client = $this->prepareJsonApiClient($remote);

    $response = $this->requestService->request($http_client, 'GET', 'entity_share');
    $json = Json::decode((string) $response->getBody());

    return $json['data']['channels'];
  }

}
