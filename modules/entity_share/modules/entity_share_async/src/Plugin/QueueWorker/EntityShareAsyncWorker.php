<?php

declare(strict_types = 1);

namespace Drupal\entity_share_async\Plugin\QueueWorker;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_async\Service\QueueHelperInterface;
use Drupal\entity_share_client\Service\JsonapiHelperInterface;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Drupal\entity_share_client\Service\RequestServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Asynchronous import queue worker.
 *
 * @QueueWorker(
 *   id = "entity_share_async_import",
 *   title = @Translation("Entity Share asynchronous import"),
 *   cron = {"time" = 30}
 * )
 */
class EntityShareAsyncWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $loggerChannelFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  private $remoteManager;

  /**
   * The jsonapi helper service.
   *
   * @var \Drupal\entity_share_client\Service\JsonapiHelperInterface
   */
  private $jsonapiHelper;

  /**
   * The request service.
   *
   * @var \Drupal\entity_share_client\Service\RequestServiceInterface
   */
  private $request;

  /**
   * The state storage.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $stateStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelFactoryInterface $logger_channel_factory,
    EntityTypeManagerInterface $entity_type_manager,
    RemoteManagerInterface $remote_manager,
    JsonapiHelperInterface $jsonapi_helper,
    RequestServiceInterface $request,
    StateInterface $state_storage
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->loggerChannelFactory = $logger_channel_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->remoteManager = $remote_manager;
    $this->jsonapiHelper = $jsonapi_helper;
    $this->request = $request;
    $this->stateStorage = $state_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_share_client.remote_manager'),
      $container->get('entity_share_client.jsonapi_helper'),
      $container->get('entity_share_client.request'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    $async_states = $this->stateStorage->get(QueueHelperInterface::STATE_ID, []);

    $remote = $this->entityTypeManager->getStorage('remote')->load($item['remote_id']);

    $channel_infos = $this->remoteManager->getChannelsInfos($remote);
    $this->jsonapiHelper->setRemote($remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($remote);

    $url = $channel_infos[$item['channel_id']]['url'];
    $parsed_url = UrlHelper::parse($url);
    $query = $parsed_url['query'];
    $query['filter']['uuid-filter'] = [
      'condition' => [
        'path' => 'id',
        'operator' => 'IN',
        'value' => [$item['uuid']],
      ],
    ];
    $query = UrlHelper::buildQuery($query);
    $prepared_url = $parsed_url['path'] . '?' . $query;

    // Get the entity json data.
    $response = $this->request->request($http_client, 'GET', $prepared_url);
    $json = Json::decode((string) $response->getBody());

    // Import the entity.
    $id = $this->jsonapiHelper->importEntityListData(EntityShareUtility::prepareData($json['data']));

    if (empty($id)) {
      $this->loggerChannelFactory->get('entity_share_async')->warning(
        "Cannot synchronise item @uuid from channel @channel_id of remote @remote_id",
        [
          '@uuid' => $item['uuid'],
          '@channel_id' => $item['channel_id'],
          '@remote_id' => $item['remote_id'],
        ]
      );
    }

    if (isset($async_states[$item['remote_id']][$item['channel_id']][$item['uuid']])) {
      unset($async_states[$item['remote_id']][$item['channel_id']][$item['uuid']]);
    }

    // Update states.
    $this->stateStorage->set(QueueHelperInterface::STATE_ID, $async_states);
  }

}
