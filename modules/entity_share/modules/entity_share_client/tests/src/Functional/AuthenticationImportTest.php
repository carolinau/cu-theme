<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\ImportContext;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;

/**
 * Functional test class for import with different authentications.
 *
 * @group entity_share
 * @group entity_share_client
 */
class AuthenticationImportTest extends EntityShareClientFunctionalTestBase {

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
   * The Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->configFactory = $this->container->get('config.factory');

    Role::load(AccountInterface::ANONYMOUS_ROLE)
      ->grantPermission('entity_share_server_access_channels')
      ->save();

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getChannelUserPermissions() {
    return [
      'entity_share_server_access_channels',
      // This user will act an administrative user, so allow them to access
      // unpublished nodes.
      'bypass node access',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function createChannel(UserInterface $user) {
    // Parent class will create the channel for authenticated (admin) user.
    parent::createChannel($user);

    // Now we create the channel for the anonymous user.
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => static::$entityTypeId . '_' . static::$entityBundleId . '_' . static::$entityLangcode . '_anon',
      'label' => $this->randomString(),
      'channel_entity_type' => static::$entityTypeId,
      'channel_bundle' => static::$entityBundleId,
      'channel_langcode' => static::$entityLangcode,
      'authorized_users' => [
        'anonymous',
      ],
    ]);
    $channel->save();
    $this->channels[$channel->id()] = $channel;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          'es_test_node_import_published' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          'es_test_node_import_not_published' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::NOT_PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test that correct entities are created with different authentications.
   */
  public function testImport() {
    // First test content creation as authenticated (ie. administrative)
    // user: both published and unpublished nodes should be created.
    $this->pullChannel('node_es_test_en');
    $this->checkCreatedEntities();

    // Delete all "client" entities created after the first import.
    $this->resetImportedContent();

    // Now we alter the remote by removing the basic auth, thus we simulate
    // being an anonymous user.
    $this->remote->set('basic_auth_username', '');
    $this->remote->set('basic_auth_password', '');
    $this->remote->save();
    // Since the remote ID remains the same, we need to reset some of
    // remote manager's cached values.
    $this->resetRemoteCaches();

    // Reset "remote" response mapping (ie. cached JSON:API responses).
    $this->remoteManager->resetResponseMapping();

    // Prepare the "server" content again.
    $this->prepareContent();

    // Re-import data from JSON:API.
    // Get JSON data from the remote anonymous channel.
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $channel_url = $channel_infos['node_es_test_en_anon']['url'];
    $response = $this->remoteManager->jsonApiRequest($this->remote, 'GET', $channel_url);
    $json = Json::decode((string) $response->getBody());
    // Clean up the "server" content.
    $this->deleteContent();
    $this->entities = [];
    // Launch the import.
    $import_context = new ImportContext($this->remote->id(), 'node_es_test_en_anon', $this::IMPORT_CONFIG_ID);
    $this->importService->prepareImport($import_context);
    $this->importService->importEntityListData(EntityShareUtility::prepareData($json['data']));

    // Assertions.
    $entity_storage = $this->entityTypeManager->getStorage('node');

    $published = $entity_storage->loadByProperties(['uuid' => 'es_test_node_import_published']);
    $this->assertEqual(count($published), 1);

    $not_published = $entity_storage->loadByProperties(['uuid' => 'es_test_node_import_not_published']);
    $this->assertEqual(count($not_published), 0);
  }

  /**
   * Helper function: unsets remote manager's cached data.
   *
   * This is needed because our remote ID is not changing, and remote manager
   * caches certain values based on the remote ID.
   * Another solution would be to reinitialize $this->remoteManager and create
   * new remote.
   */
  protected function resetRemoteCaches() {
    $this->remoteManager->resetRemoteInfos();
    $this->remoteManager->resetHttpClientsCache('json_api');
  }

}
