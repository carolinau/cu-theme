<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\Event\EntityListDataAlterEvent;
use Drupal\entity_share_client\Service\JsonapiHelperInterface;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Drupal\entity_share_client\Service\RequestServiceInterface;
use GuzzleHttp\Client;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class BlockFieldBlockContentImporter.
 *
 * @package Drupal\entity_share_client
 */
class BlockFieldBlockContentImporter implements EventSubscriberInterface {

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The jsonapi helper.
   *
   * @var \Drupal\entity_share_client\Service\JsonapiHelperInterface
   */
  protected $jsonapiHelper;

  /**
   * The request service.
   *
   * @var \Drupal\entity_share_client\Service\RequestServiceInterface
   */
  protected $requestService;

  /**
   * Constructor.
   *
   * @param \Drupal\entity_share_client\Service\RemoteManagerInterface $remote_manager
   *   The remote manager.
   * @param \Drupal\entity_share_client\Service\JsonapiHelperInterface $jsonapi_helper
   *   The jsonapi helper.
   * @param \Drupal\entity_share_client\Service\RequestServiceInterface $request_service
   *   The request service.
   */
  public function __construct(
    RemoteManagerInterface $remote_manager,
    JsonapiHelperInterface $jsonapi_helper,
    RequestServiceInterface $request_service
  ) {
    $this->remoteManager = $remote_manager;
    $this->jsonapiHelper = $jsonapi_helper;
    $this->requestService = $request_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[EntityListDataAlterEvent::EVENT_NAME] = ['importBlockContentEntities', 100];
    return $events;
  }

  /**
   * Import block contents from block field.
   *
   * @param \Drupal\entity_share_client\Event\EntityListDataAlterEvent $event
   *   The event containing the entity list data.
   */
  public function importBlockContentEntities(EntityListDataAlterEvent $event) {
    $remote = $event->getRemote();
    $http_client = $this->remoteManager->prepareJsonApiClient($remote);

    $entity_list_data = $event->getEntityListData();
    $entity_list_data = EntityShareUtility::prepareData($entity_list_data);

    // Parse entity list data to extract urls to get block content from block
    // field. And remove this info.
    foreach ($entity_list_data as $key => $entity_data) {
      if (isset($entity_data['attributes']) && is_array($entity_data['attributes'])) {
        foreach ($entity_data['attributes'] as $field_name => $field_data) {
          if (is_array($field_data)) {
            if (EntityShareUtility::isNumericArray($field_data)) {
              foreach ($field_data as $delta => $value) {
                if (isset($value['block_content_href'])) {
                  $this->importBlockContent($http_client, $value['block_content_href']);
                  unset($entity_list_data[$key]['attributes'][$field_name][$delta]['block_content_href']);
                }
              }
            }
            elseif (isset($field_data['block_content_href'])) {
              $this->importBlockContent($http_client, $field_data['block_content_href']);
              unset($entity_list_data[$key]['attributes'][$field_name]['block_content_href']);
            }
          }
        }
      }
    }

    $event->setEntityListData($entity_list_data);
  }

  /**
   * Helper function.
   *
   * @param \GuzzleHttp\Client $http_client
   *   The HTTP client.
   * @param string $url
   *   The URL to request to get the block content.
   */
  protected function importBlockContent(Client $http_client, $url) {
    $block_entity_response = $this->requestService->request($http_client, 'GET', $url);
    $block_entity_json = Json::decode((string) $block_entity_response->getBody());

    // $block_entity_json['data'] can be null in the case of
    // missing/deleted referenced entities.
    if (!isset($block_entity_json['errors']) && !is_null($block_entity_json['data'])) {
      $this->jsonapiHelper->importEntityListData($block_entity_json['data']);
    }
  }

}
