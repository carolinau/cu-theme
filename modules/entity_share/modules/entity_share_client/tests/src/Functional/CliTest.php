<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Component\Utility\UrlHelper;
use Drupal\node\NodeInterface;

/**
 * General functional test class for CLI integration.
 *
 * @group entity_share
 * @group entity_share_client
 */
class CliTest extends EntityShareClientFunctionalTestBase {

  /**
   * The state information service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $stateInformation;

  /**
   * The CLI service.
   *
   * @var \Drupal\entity_share_client\Service\EntityShareClientCliService
   */
  protected $cliService;

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  protected static $entityBundleId = 'es_test';

  /**
   * {@inheritdoc}
   */
  protected static $entityLangcode = 'en';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->stateInformation = $this->container->get('entity_share_client.state_information');
    $this->cliService = $this->container->get('entity_share_client.cli');

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          'es_test_1' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          'es_test_2' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test pull command.
   */
  public function testPull() {
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $channel_info = array_shift($channel_infos);
    $channel_url = $channel_info['url'];

    $this->cliService->pull($this->remote, $channel_url);
    $this->checkCreatedEntities();
  }

  /**
   * Test pull command with the update option.
   */
  public function testPullUpdate() {
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $channel_info = array_shift($channel_infos);
    $channel_url = $channel_info['url'];
    $channel_url_uuid = $channel_info['url_uuid'];

    $update_count = $this->cliService->pullUpdates($this->remote, $channel_url, $channel_url_uuid, 'node');
    $this->jsonapiHelper->clearImportedEntities();
    $this->assertEquals(2, $update_count, 'On the first run, the two contents had been pulled.');

    $this->checkCreatedEntities();

    $update_count = $this->cliService->pullUpdates($this->remote, $channel_url, $channel_url_uuid, 'node');
    $this->jsonapiHelper->clearImportedEntities();
    $this->assertEquals(0, $update_count, 'On the second run, no content had been pulled.');

    // Edit the first content (also this emulates a change on the client
    // website).
    $node = $this->loadEntity('node', 'es_test_1');
    $node->set('title', $this->randomString());
    $node->setChangedTime($this->faker->unixTime());
    $node->save();

    $update_count = $this->cliService->pullUpdates($this->remote, $channel_url, $channel_url_uuid, 'node');
    $this->jsonapiHelper->clearImportedEntities();
    $this->assertEquals(1, $update_count, 'On the third run, only the changed content had been pulled.');

    $this->checkCreatedEntities();

    $update_count = $this->cliService->pullUpdates($this->remote, $channel_url, $channel_url_uuid, 'node');
    $this->jsonapiHelper->clearImportedEntities();
    $this->assertEquals(0, $update_count, 'On the fourth run, no content had been pulled.');
  }

  /**
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();

    // Needs to make the requests when only es_test_1 will be required.
    $selected_entities = [
      'es_test_1',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->jsonapiHelper->setRemote($this->remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($this->remote);
    $this->discoverJsonApiEndpoints($http_client, $prepared_url);

    // Needs to make the requests when both contents will be required.
    $selected_entities = [
      'es_test_1',
      'es_test_2',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->jsonapiHelper->setRemote($this->remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($this->remote);
    $this->discoverJsonApiEndpoints($http_client, $prepared_url);

    // Because of the offset, needs to parse the url_uuid in a special way.
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $channel_url_uuid = $channel_infos['node_es_test_en']['url_uuid'];
    $offset = 0;
    $parsed_url = UrlHelper::parse($channel_url_uuid);
    $parsed_url['query']['page']['offset'] = $offset;
    $query = UrlHelper::buildQuery($parsed_url['query']);
    $revisions_url = $parsed_url['path'] . '?' . $query;
    $this->jsonapiHelper->setRemote($this->remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($this->remote);
    $this->discoverJsonApiEndpoints($http_client, $revisions_url);
  }

}
