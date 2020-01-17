<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\Entity\Remote;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Utility\Timer;
use Drupal\entity_share_client\Entity\RemoteInterface;

/**
 * Class EntityShareClientCliService.
 *
 * @package Drupal\entity_share_client
 *
 * @internal This service is not an api and may change at any time.
 */
class EntityShareClientCliService {

  /**
   * Drupal\Core\StringTranslation\TranslationManager definition.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $stringTranslation;

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
   * List of messages.
   *
   * @var array
   */
  protected $errors;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request service.
   *
   * @var \Drupal\entity_share_client\Service\RequestServiceInterface
   */
  protected $requestService;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\entity_share_client\Service\RemoteManagerInterface $remote_manager
   *   The remote manager service.
   * @param \Drupal\entity_share_client\Service\JsonapiHelperInterface $jsonapi_helper
   *   The jsonapi helper service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\entity_share_client\Service\RequestServiceInterface $request_service
   *   The request service.
   */
  public function __construct(
    TranslationInterface $string_translation,
    RemoteManagerInterface $remote_manager,
    JsonapiHelperInterface $jsonapi_helper,
    EntityTypeManagerInterface $entity_type_manager,
    RequestServiceInterface $request_service
  ) {
    $this->stringTranslation = $string_translation;
    $this->remoteManager = $remote_manager;
    $this->jsonapiHelper = $jsonapi_helper;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestService = $request_service;
    $this->errors = [];
  }

  /**
   * Handle the pull interaction.
   *
   * @param string $remote_id
   *   The remote website id to import from.
   * @param string $channel_id
   *   The remote channel id to import.
   * @param \Symfony\Component\Console\Style\StyleInterface|\ConfigSplitDrush8Io $io
   *   The $io interface of the cli tool calling.
   * @param callable $t
   *   The translation function akin to t().
   */
  public function ioPull($remote_id, $channel_id, $io, callable $t) {
    Timer::start('io-pull');
    /** @var \Drupal\entity_share_client\Entity\RemoteInterface $remotes */
    $remotes = Remote::loadMultiple();

    // Check that the remote website exists.
    if (!isset($remotes[$remote_id])) {
      $io->error($t('There is no remote website configured with the id: @remote_id.', ['@remote_id' => $remote_id]));
      return;
    }

    $remote = $remotes[$remote_id];
    $channel_infos = $this->remoteManager->getChannelsInfos($remote);

    // Check that the channel exists.
    if (!isset($channel_infos[$channel_id])) {
      $io->error($t('There is no channel configured or accessible with the id: @channel_id.', ['@channel_id' => $channel_id]));
      return;
    }

    $this->pull($remote, $channel_infos[$channel_id]['url']);
    Timer::stop('io-pull');
    $io->success($t('Channel successfully pulled. Execution time @time ms.', ['@time' => Timer::read('io-pull')]));
  }

  /**
   * Pull content.
   *
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote website entity to import from.
   * @param string $channel_url
   *   The remote channel URL to import.
   */
  public function pull(RemoteInterface $remote, $channel_url) {
    // Import channel content and loop on pagination.
    $this->jsonapiHelper->setRemote($remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($remote);
    while ($channel_url) {
      $response = $this->requestService->request($http_client, 'GET', $channel_url);
      $json = Json::decode((string) $response->getBody());
      $this->jsonapiHelper->importEntityListData(EntityShareUtility::prepareData($json['data']));
      if (isset($json['links']['next']['href'])) {
        $channel_url = $json['links']['next']['href'];
      }
      else {
        $channel_url = FALSE;
      }
    }
  }

  /**
   * Handle the pull updates interaction.
   *
   * @param string $remote_id
   *   The remote website id to import from.
   * @param string $channel_id
   *   The remote channel id to import.
   * @param \Symfony\Component\Console\Style\StyleInterface|\ConfigSplitDrush8Io $io
   *   The $io interface of the cli tool calling.
   * @param callable $t
   *   The translation function akin to t().
   */
  public function ioPullUpdates($remote_id, $channel_id, $io, callable $t) {
    Timer::start('io-pull-updates');
    /** @var \Drupal\entity_share_client\Entity\RemoteInterface $remotes */
    $remotes = Remote::loadMultiple();

    // Check that the remote website exists.
    if (!isset($remotes[$remote_id])) {
      $io->error($t('There is no remote website configured with the id: @remote_id.', ['@remote_id' => $remote_id]));
      return;
    }

    $remote = $remotes[$remote_id];
    $channel_infos = $this->remoteManager->getChannelsInfos($remote);

    // Check that the channel exists.
    if (!isset($channel_infos[$channel_id])) {
      $io->error($t('There is no channel configured or accessible with the id: @channel_id.', ['@channel_id' => $channel_id]));
      return;
    }

    $update_count = $this->pullUpdates($remote, $channel_infos[$channel_id]['url'], $channel_infos[$channel_id]['url_uuid'], $channel_infos[$channel_id]['channel_entity_type']);
    Timer::stop('io-pull-updates');
    $io->success($t('Channel successfully pulled. Number of updated entities: @count, execution time: @time ms', ['@count' => $update_count, '@time' => Timer::read('io-pull-updates')]));
  }

  /**
   * Pull changed content.
   *
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote website entity to import from.
   * @param string $channel_url
   *   The remote channel URL to import.
   * @param string $channel_url_uuid
   *   The remote channel URL to import keyed by 'url_uuid' in the channel
   *   infos.
   * @param string $entity_type_id
   *   The entity type ID to import.
   *
   * @return int
   *   The number of updated content.
   */
  public function pullUpdates(RemoteInterface $remote, $channel_url, $channel_url_uuid, $entity_type_id) {
    // Import channel content and loop on pagination.
    $this->jsonapiHelper->setRemote($remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($remote);
    $original_channel_url = $channel_url;

    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $offset = 0;
    $update_count = 0;

    while ($channel_url) {
      // Offset pagination.
      $parsed_url = UrlHelper::parse($channel_url_uuid);
      $parsed_url['query']['page']['offset'] = $offset;
      $query = UrlHelper::buildQuery($parsed_url['query']);
      $revisions_url = $parsed_url['path'] . '?' . $query;

      // Get UUIDs and update timestamps from next page in a row.
      $response = $this->requestService->request($http_client, 'GET', $revisions_url);
      $revisions_json = Json::decode((string) $response->getBody());

      $uuids = [];
      foreach ($revisions_json['data'] as $row) {
        // Look for query with the same UUID and changed timestamp,
        // if that entity doesn't exist it means we need to pull it from remote
        // channel.
        $changed_datetime_timestamp = 0;
        // If the website is using backward compatible timestamps output.
        // @see https://www.drupal.org/node/2859657.
        if (is_numeric($row['attributes']['changed'])) {
          // The value is casted in integer for
          // https://www.drupal.org/node/2837696.
          $changed_datetime_timestamp = (int) $row['attributes']['changed'];
        }
        elseif ($changed_datetime = \DateTime::createFromFormat(\DateTime::RFC3339, $row['attributes']['changed'])) {
          $changed_datetime_timestamp = $changed_datetime->getTimestamp();
        }

        $entity_changed = $storage->getQuery()
          ->condition('uuid', $row['id'])
          ->condition('changed', $changed_datetime_timestamp)
          ->count()
          ->execute();
        if ($entity_changed == 0) {
          $uuids[] = $row['id'];
        }
      }

      if (!empty($uuids)) {
        // Prepare JSON filter query string.
        $filter = [
          'filter' => [
            'uuid-filter' => [
              'condition' => [
                'path' => 'id',
                'operator' => 'IN',
                'value' => $uuids,
              ],
            ],
          ],
        ];

        // Call remote channel and fetch content of entities which should be
        // updated.
        $parsed_original_channel_url = UrlHelper::parse($original_channel_url);
        $filter_query = $parsed_original_channel_url['query'];
        $filter_query = array_merge_recursive($filter_query, $filter);
        $filter_query = UrlHelper::buildQuery($filter_query);
        $filtered_url = $parsed_original_channel_url['path'] . '?' . $filter_query;

        $response = $this->requestService->request($http_client, 'GET', $filtered_url);
        $json = Json::decode((string) $response->getBody());
        $imported_entities = $this->jsonapiHelper->importEntityListData(EntityShareUtility::prepareData($json['data']));
        $update_count += count($imported_entities);
      }

      if (isset($revisions_json['links']['next']['href'])) {
        $channel_url = $revisions_json['links']['next']['href'];
      }
      else {
        $channel_url = FALSE;
      }

      // Update page number and offset for next API call.
      $offset += 50;
    }
    return $update_count;
  }

  /**
   * Returns error messages created while running the import.
   *
   * @return array
   *   List of messages.
   */
  public function getErrors() {
    return $this->errors;
  }

}
