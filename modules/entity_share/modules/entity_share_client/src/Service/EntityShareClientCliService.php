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

    // Import channel content and loop on pagination.
    $this->jsonapiHelper->setRemote($remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($remote);
    $channel_url = $channel_infos[$channel_id]['url'];
    while ($channel_url) {
      $io->text($t('Beginning to import content from URL: @url', ['@url' => $channel_url]));

      $response = $this->requestService->request($http_client, 'GET', $channel_url);
      $json = Json::decode((string) $response->getBody());
      $imported_entities = $this->jsonapiHelper->importEntityListData(EntityShareUtility::prepareData($json['data']));

      $io->text($t('@number entities have been imported.', ['@number' => count($imported_entities)]));

      if (isset($json['links']['next']['href'])) {
        $channel_url = $json['links']['next']['href'];
      }
      else {
        $channel_url = FALSE;
      }
    }
    Timer::stop('io-pull');
    $io->success($t('Channel successfully pulled. Execution time @time ms.', ['@time' => Timer::read('io-pull')]));
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

    // Import channel content and loop on pagination.
    $this->jsonapiHelper->setRemote($remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($remote);
    $channel_url = $channel_infos[$channel_id]['url'];

    $storage = $this->entityTypeManager->getStorage($channel_infos[$channel_id]['channel_entity_type']);
    $offset = 0;
    $update_count = 0;

    $io->text($t('Looking for new content in channel @channel', ['@channel' => $channel_id]));

    while ($channel_url) {
      // Offset pagination.
      $parsed_url = UrlHelper::parse($channel_infos[$channel_id]['url_uuid']);
      $parsed_url['query']['page']['offset'] = $offset;
      $query = UrlHelper::buildQuery($parsed_url['query']);
      $revisions_url = $parsed_url['path'] . '?' . $query;
      $io->text($t('Looking for updated content at URL: @url', ['@url' => $revisions_url]));

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

        $entityChanged = $storage->getQuery()
          ->condition('uuid', $row['id'])
          ->condition('changed', $changed_datetime_timestamp)
          ->count()
          ->execute();
        if ($entityChanged == 0) {
          $uuids[] = $row['id'];
        }
      }

      if (!empty($uuids)) {
        // Prepare JSON filter query string.
        $filter = [
          'filter' => [
            'uuid' => [
              'path' => 'id',
              'value' => $uuids,
              'operator' => 'IN',
            ],
          ],
        ];

        // Call remote channel and fetch content of entities which should be
        // updated.
        $filter_query = UrlHelper::buildQuery($filter);
        $filtered_url = $channel_infos[$channel_id]['url'] . '?' . $filter_query;

        $response = $this->requestService->request($http_client, 'GET', $filtered_url);
        $json = Json::decode((string) $response->getBody());
        $imported_entities = $this->jsonapiHelper->importEntityListData(EntityShareUtility::prepareData($json['data']));
        $io->text($t('@number entities have been imported.', ['@number' => count($imported_entities)]));
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
    Timer::stop('io-pull-updates');
    $io->success($t('Channel successfully pulled. Number of updated entities: @count, execution time: @time ms', ['@count' => $update_count, '@time' => Timer::read('io-pull-updates')]));
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
