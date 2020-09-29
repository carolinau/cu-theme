<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Http\ClientFactory;
use Drupal\entity_share_client\Entity\RemoteInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;

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
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * HTTP clients prepared per remote.
   *
   * @var \GuzzleHttp\ClientInterface[]
   */
  protected $httpClients = [];

  /**
   * HTTP clients prepared for JSON:API endpoints per remotes.
   *
   * @var \GuzzleHttp\ClientInterface[]
   */
  protected $jsonApiHttpClients = [];

  /**
   * Data provided by entity_share entry point per remote.
   *
   * @var array
   */
  protected $remoteInfos = [];

  /**
   * RemoteManager constructor.
   *
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   *   The HTTP client factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    ClientFactory $http_client_factory,
    LoggerInterface $logger
  ) {
    $this->httpClientFactory = $http_client_factory;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function request(RemoteInterface $remote, $method, $url) {
    $client = $this->getHttpClient($remote);
    return $this->doRequest($client, $method, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function jsonApiRequest(RemoteInterface $remote, $method, $url) {
    $client = $this->getJsonApiHttpClient($remote);
    return $this->doRequest($client, $method, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function getChannelsInfos(RemoteInterface $remote) {
    $remote_id = $remote->id();
    if (!isset($this->remoteInfos[$remote_id])) {
      $response = $this->jsonApiRequest($remote, 'GET', 'entity_share');
      $json = [
        'data' => [
          'channels' => [],
          'field_mappings' => [],
        ],
      ];
      if (!is_null($response)) {
        $json = Json::decode((string) $response->getBody());
      }
      $this->remoteInfos[$remote_id] = $json['data'];
    }

    return $this->remoteInfos[$remote_id]['channels'];
  }

  /**
   * {@inheritdoc}
   */
  public function getfieldMappings(RemoteInterface $remote) {
    $remote_id = $remote->id();
    if (!isset($this->remoteInfos[$remote_id])) {
      $response = $this->jsonApiRequest($remote, 'GET', 'entity_share');
      $json = Json::decode((string) $response->getBody());
      $this->remoteInfos[$remote_id] = $json['data'];
    }

    return $this->remoteInfos[$remote_id]['field_mappings'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getHttpClient(RemoteInterface $remote) {
    $remote_id = $remote->id();
    if (!isset($this->httpClients[$remote_id])) {
      $http_client = $this->httpClientFactory->fromOptions([
        'base_uri' => $remote->get('url') . '/',
        'cookies' => TRUE,
        'allow_redirects' => TRUE,
      ]);

      if ($remote->get('basic_auth_username') && $remote->get('basic_auth_password')) {
        $http_client->post('user/login', [
          'form_params' => [
            'name' => $remote->get('basic_auth_username'),
            'pass' => $remote->get('basic_auth_password'),
            'form_id' => 'user_login_form',
          ],
        ]);
      }

      $this->httpClients[$remote_id] = $http_client;
    }

    return $this->httpClients[$remote_id];
  }

  /**
   * {@inheritdoc}
   */
  protected function getJsonApiHttpClient(RemoteInterface $remote) {
    $remote_id = $remote->id();
    if (!isset($this->jsonApiHttpClients[$remote_id])) {
      $options = [
        'base_uri' => $remote->get('url') . '/',
        'headers' => [
          'Content-type' => 'application/vnd.api+json',
        ],
      ];

      if ($remote->get('basic_auth_username') && $remote->get('basic_auth_password')) {
        $options += [
          'auth' => [
            $remote->get('basic_auth_username'),
            $remote->get('basic_auth_password'),
          ],
        ];
      }

      $this->jsonApiHttpClients[$remote_id] = $this->httpClientFactory->fromOptions($options);
    }

    return $this->jsonApiHttpClients[$remote_id];
  }

  /**
   * Performs a HTTP request.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The client which will do the request.
   * @param string $method
   *   HTTP method.
   * @param string $url
   *   URL to request.
   *
   * @return \Psr\Http\Message\ResponseInterface||null
   *   The response or NULL if a problem occured.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function doRequest(ClientInterface $client, $method, $url) {
    $log_variables = [
      '@url' => $url,
      '@method' => $method,
    ];

    try {
      return $client->request($method, $url);
    }
    catch (ClientException $exception) {
      $log_variables['@exception_message'] = $exception->getMessage();
      $this->logger->error('Client exception when requesting the URL: @url with method @method: @exception_message', $log_variables);
    }
    catch (ServerException $exception) {
      $log_variables['@exception_message'] = $exception->getMessage();
      $this->logger->error('Server exception when requesting the URL: @url with method @method: @exception_message', $log_variables);
    }
    catch (GuzzleException $exception) {
      $log_variables['@exception_message'] = $exception->getMessage();
      $this->logger->error('Guzzle exception when requesting the URL: @url with method @method: @exception_message', $log_variables);
    }
    catch (\Exception $exception) {
      $log_variables['@exception_message'] = $exception->getMessage();
      $this->logger->error('Error when requesting the URL: @url with method @method: @exception_message', $log_variables);
    }

    return NULL;
  }

}
