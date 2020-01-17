<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_test\EntityFieldHelperTrait;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\entity_share_server\Functional\EntityShareServerRequestTestTrait;
use Drupal\Tests\RandomGeneratorTrait;
use Drupal\user\UserInterface;
use Faker\Factory;
use Faker\Provider\fr_FR\PhoneNumber;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;

/**
 * Base class for Entity share server functional tests.
 */
abstract class EntityShareClientFunctionalTestBase extends BrowserTestBase {

  use RandomGeneratorTrait;
  use EntityShareServerRequestTestTrait;
  use EntityFieldHelperTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_share_client_request_test',
    'entity_share_client',
    'entity_share_server',
    'entity_share_test',
    'basic_auth',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * The tested entity type.
   *
   * @var string
   */
  protected static $entityTypeId = NULL;

  /**
   * The tested entity type bundle.
   *
   * @var string
   */
  protected static $entityBundleId = NULL;

  /**
   * The tested entity langcode.
   *
   * @var string
   */
  protected static $entityLangcode = NULL;

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A test user with access to the channel list.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $channelUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The remote manager service.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The request service.
   *
   * @var \Drupal\entity_share_client\Service\RequestServiceInterface
   */
  protected $requestService;

  /**
   * The jsonapi helper.
   *
   * @var \Drupal\entity_share_client\Service\JsonapiHelperInterface
   */
  protected $jsonapiHelper;

  /**
   * Faker generator.
   *
   * @var \Faker\Generator
   */
  protected $faker;

  /**
   * The visited URLs during setup.
   *
   * Prevents infinite loop during preparation of website emulation.
   *
   * @var string[]
   */
  protected $visitedUrlsDuringSetup = [];

  /**
   * The remote used for the test.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface
   */
  protected $remote;

  /**
   * The channels used for the test.
   *
   * @var \Drupal\entity_share_server\Entity\ChannelInterface[]
   */
  protected $channels = [];

  /**
   * A mapping of the entities created for the test.
   *
   * With the following structure:
   * [
   *   'entityTypeId' => [
   *     Entity object,
   *   ],
   * ]
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface[][]
   */
  protected $entities = [];

  /**
   * A mapping of the entity data used for the test.
   *
   * @var array
   */
  protected $entitiesData;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Prepare users.
    $this->adminUser = $this->drupalCreateUser($this->getAdministratorPermissions());
    $this->channelUser = $this->drupalCreateUser($this->getChannelUserPermissions());

    // Retrieve required services.
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->remoteManager = $this->container->get('entity_share_client.remote_manager');
    $this->requestService = $this->container->get('entity_share_client.request');
    $this->jsonapiHelper = $this->container->get('entity_share_client.jsonapi_helper');
    $this->faker = Factory::create();
    // Add French phone number.
    $this->faker->addProvider(new PhoneNumber($this->faker));

    $this->createRemote($this->channelUser);
    $this->createChannel($this->channelUser);
  }

  /**
   * Helper function.
   *
   * Need to separate those steps from the setup in the base class, because some
   * sub-class setup may change the content of the fixture.
   */
  protected function postSetupFixture() {
    $this->prepareContent();
    $this->populateRequestService();
    $this->deleteContent();
  }

  /**
   * Gets the permissions for the admin user.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getAdministratorPermissions() {
    return [
      'view the administration theme',
      'access administration pages',
      'administer_channel_entity',
    ];
  }

  /**
   * Gets the permissions for the channel user.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getChannelUserPermissions() {
    return [
      'entity_share_server_access_channels',
    ];
  }

  /**
   * Returns Guzzle request options for authentication.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to authenticate with.
   *
   * @return array
   *   Guzzle request options to use for authentication.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function getAuthenticationRequestOptions(AccountInterface $account) {
    return [
      RequestOptions::HEADERS => [
        'Authorization' => 'Basic ' . base64_encode($account->getAccountName() . ':' . $account->passRaw),
      ],
    ];
  }

  /**
   * Helper function to create the remote that point to the site itself.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user which credential will be used for the remote.
   */
  protected function createRemote(UserInterface $user) {
    $remote_storage = $this->entityTypeManager->getStorage('remote');
    $remote = $remote_storage->create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'url' => $this->buildUrl('<front>'),
      'basic_auth_username' => $user->getAccountName(),
      'basic_auth_password' => $user->passRaw,
    ]);
    $remote->save();
    $this->remote = $remote;
  }

  /**
   * Helper function to create the channel used for the test.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user which credential will be used for the remote.
   */
  protected function createChannel(UserInterface $user) {
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => static::$entityTypeId . '_' . static::$entityBundleId . '_' . static::$entityLangcode,
      'label' => $this->randomString(),
      'channel_entity_type' => static::$entityTypeId,
      'channel_bundle' => static::$entityBundleId,
      'channel_langcode' => static::$entityLangcode,
      'authorized_users' => [
        $user->uuid(),
      ],
    ]);
    $channel->save();
    $this->channels[$channel->id()] = $channel;
  }

  /**
   * Helper function to create the content required for the tests.
   */
  protected function prepareContent() {
    $entities_data = $this->getEntitiesData();

    foreach ($entities_data as $entity_type_id => $data_per_languages) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);

      if (!isset($this->entities[$entity_type_id])) {
        $this->entities[$entity_type_id] = [];
      }

      foreach ($data_per_languages as $langcode => $entity_data) {
        foreach ($entity_data as $entity_uuid => $entity_data_per_field) {
          // If the entity has already been created, create a translation.
          if (isset($this->entities[$entity_type_id][$entity_uuid])) {
            $prepared_entity_data = $this->prepareEntityData($entity_data_per_field);
            $entity = $this->entities[$entity_type_id][$entity_uuid];
            $entity->addTranslation($langcode, $prepared_entity_data);
            $entity->save();
          }
          else {
            $entity_data_per_field += [
              'langcode' => [
                'value' => $langcode,
                'checker_callback' => 'getValue',
              ],
              'uuid' => [
                'value' => $entity_uuid,
                'checker_callback' => 'getValue',
              ],
            ];
            $prepared_entity_data = $this->prepareEntityData($entity_data_per_field);

            $entity = $entity_storage->create($prepared_entity_data);
            $entity->save();
          }

          $this->entities[$entity_type_id][$entity_uuid] = $entity;
        }
      }
    }
  }

  /**
   * Helper function to prepare entity data.
   *
   * Get an array usable to create entity or translation.
   *
   * @param array $entityData
   *   The entity data as in getEntitiesData().
   *
   * @return array
   *   The array of prepared values.
   */
  protected function prepareEntityData(array $entityData) {
    $prepared_entity_data = [];

    foreach ($entityData as $field_machine_name => $data) {
      // Some data are dynamic.
      if (isset($data['value_callback'])) {
        $prepared_entity_data[$field_machine_name] = call_user_func($data['value_callback']);
      }
      else {
        $prepared_entity_data[$field_machine_name] = $data['value'];
      }
    }

    return $prepared_entity_data;
  }

  /**
   * Helper function to get a mapping of the entities data.
   *
   * Used to create the entities for the test and to test that it has been
   * recreated properly.
   */
  abstract protected function getEntitiesDataArray();

  /**
   * Helper function to get a mapping of the entities data.
   *
   * Used to create the entities for the test and to test that it has been
   * recreated properly.
   */
  protected function getEntitiesData() {
    if (!isset($this->entitiesData)) {
      $this->entitiesData = $this->getEntitiesDataArray();
    }

    return $this->entitiesData;
  }

  /**
   * Helper function to populate the request service with responses.
   */
  protected function populateRequestService() {
    // Do not use RemoteManager::getChannelsInfos so we are able to test
    // behavior with website in subdirectory on testbot.
    $entity_share_entrypoint_url = Url::fromRoute('entity_share_server.resource_list');
    $http_client = $this->remoteManager->prepareJsonApiClient($this->remote);

    $response = $this->requestService->request($http_client, 'GET', $entity_share_entrypoint_url->setAbsolute()->toString());
    $json_response = Json::decode((string) $response->getBody());

    foreach ($json_response['data']['channels'] as $channel_data) {
      $this->discoverJsonApiEndpoints($http_client, $channel_data['url']);
      $this->discoverJsonApiEndpoints($http_client, $channel_data['url_uuid']);
    }
  }

  /**
   * Helper function to populate the request service with responses.
   *
   * @param \GuzzleHttp\Client $http_client
   *   The http client.
   * @param string $url
   *   THe url to request.
   */
  protected function discoverJsonApiEndpoints(Client $http_client, $url) {
    // Prevents infinite loop.
    if (in_array($url, $this->visitedUrlsDuringSetup)) {
      return;
    }
    $this->visitedUrlsDuringSetup[] = $url;

    $response = $this->requestService->request($http_client, 'GET', $url);
    $json_response = Json::decode((string) $response->getBody());

    // Loop on the data and relationships to get expected endpoints.
    if (is_array($json_response['data'])) {
      foreach (EntityShareUtility::prepareData($json_response['data']) as $data) {
        if (isset($data['relationships'])) {
          foreach ($data['relationships'] as $field_data) {
            if (isset($field_data['links']['related']['href'])) {
              $this->discoverJsonApiEndpoints($http_client, $field_data['links']['related']['href']);
            }
          }
        }

        // File entity.
        if ($data['type'] == 'file--file' && isset($data['attributes']['uri']['url'])) {
          // Need to handle exception for the test where the physical file has
          // been deleted.
          try {
            $this->requestService->request($this->remoteManager->prepareClient($this->remote), 'GET', $data['attributes']['uri']['url']);
          }
          catch (ClientException $exception) {
            // Do nothing.
          }
        }
      }
    }

    // Handle pagination.
    if (isset($json_response['links']['next']['href'])) {
      $this->discoverJsonApiEndpoints($http_client, $json_response['links']['next']['href']);
    }
  }

  /**
   * Helper function to delete the prepared content.
   */
  protected function deleteContent() {
    foreach ($this->entities as $entity_type_id => $entity_list) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);

      foreach ($entity_list as $entity_uuid => $entity) {
        $entity->delete();

        // Check that the entity has been deleted.
        $remaining_entities = $entity_storage->loadByProperties(['uuid' => $entity_uuid]);
        $this->assertTrue(empty($remaining_entities), 'The ' . $entity_type_id . ' has been deleted.');
      }
    }
  }

  /**
   * Helper function that test that the entities had been recreated.
   */
  protected function checkCreatedEntities() {
    $entities_data = $this->getEntitiesData();

    foreach ($entities_data as $entity_type_id => $data_per_languages) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);

      foreach ($data_per_languages as $language_id => $entity_data) {
        foreach ($entity_data as $entity_uuid => $entity_data_per_field) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface[] $recreated_entities */
          $recreated_entities = $entity_storage->loadByProperties(['uuid' => $entity_uuid]);

          // Check that the entity has been recreated.
          $this->assertTrue(!empty($recreated_entities), 'The ' . $entity_type_id . ' with UUID ' . $entity_uuid . ' has been recreated.');

          // Check the values.
          if (!empty($recreated_entities)) {
            $recreated_entity = array_shift($recreated_entities);

            $entity_translation = $recreated_entity->getTranslation($language_id);

            foreach ($entity_data_per_field as $field_machine_name => $data) {
              // Some data are dynamic.
              if (isset($data['value_callback'])) {
                $data['value'] = call_user_func($data['value_callback']);
              }

              // When additional keys in field data are created by Drupal. We
              // need to filter this structure.
              if ($data['checker_callback'] == 'getFilteredStructureValues') {
                // Assume that also for single value fields, the data will be
                // set using an array of values.
                $structure = array_keys($data['value'][0]);
                $this->assertEquals($data['value'], $this->getFilteredStructureValues($entity_translation, $field_machine_name, $structure), 'The data of the field ' . $field_machine_name . ' has been recreated.');
              }
              else {
                $this->assertEquals($data['value'], $this->{$data['checker_callback']}($entity_translation, $field_machine_name), 'The data of the field ' . $field_machine_name . ' has been recreated.');
              }
            }
          }
        }
      }
    }
  }

  /**
   * Helper function to import all channels.
   */
  protected function pullEveryChannels() {
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $this->jsonapiHelper->setRemote($this->remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($this->remote);

    foreach ($this->channels as $channel_id => $channel) {
      $channel_url = $channel_infos[$channel_id]['url'];
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
  }

  /**
   * Helper function to import all channels.
   *
   * @param string $channel_id
   *   The channel ID.
   */
  protected function pullChannel($channel_id) {
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $this->jsonapiHelper->setRemote($this->remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($this->remote);

    $channel_url = $channel_infos[$channel_id]['url'];
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
   * Helper function to import all channels.
   *
   * @param string $channel_id
   *   The channel ID.
   * @param string $entity_uuid
   *   The entity UUID.
   *
   * @return array
   *   An array of decoded data.
   */
  protected function getEntityJsonData($channel_id, $entity_uuid) {
    $json_data = [];
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $this->jsonapiHelper->setRemote($this->remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($this->remote);

    $channel_url = $channel_infos[$channel_id]['url'];
    while ($channel_url) {
      $response = $this->requestService->request($http_client, 'GET', $channel_url);
      $json = Json::decode((string) $response->getBody());

      $json_data = EntityShareUtility::prepareData($json['data']);

      foreach ($json_data as $entity_json_data) {
        if ($entity_json_data['id'] == $entity_uuid) {
          return $entity_json_data;
        }
      }

      if (isset($json['links']['next']['href'])) {
        $channel_url = $json['links']['next']['href'];
      }
      else {
        $channel_url = FALSE;
      }
    }

    return $json_data;
  }

  /**
   * Helper function.
   *
   * @param array $media_infos
   *   The media infos to use.
   *
   * @return array
   *   Return common part to create medias.
   */
  protected function getCompleteMediaInfos(array $media_infos) {
    return array_merge([
      'status' => [
        'value' => NodeInterface::PUBLISHED,
        'checker_callback' => 'getValue',
      ],
    ], $media_infos);
  }

  /**
   * Helper function.
   *
   * @param array $node_infos
   *   The node infos to use.
   *
   * @return array
   *   Return common part to create nodes.
   */
  protected function getCompleteNodeInfos(array $node_infos) {
    return array_merge([
      'type' => [
        'value' => static::$entityBundleId,
        'checker_callback' => 'getTargetId',
      ],
      'title' => [
        'value' => $this->randomString(),
        'checker_callback' => 'getValue',
      ],
    ], $node_infos);
  }

  /**
   * Helper function.
   *
   * @param array $taxonomy_term_infos
   *   The taxonomy term infos to use.
   *
   * @return array
   *   Return common part to create taxonomy terms.
   */
  protected function getCompleteTaxonomyTermInfos(array $taxonomy_term_infos) {
    return array_merge([
      'vid' => [
        'value' => static::$entityBundleId,
        'checker_callback' => 'getTargetId',
      ],
      'name' => [
        'value' => $this->randomString(),
        'checker_callback' => 'getValue',
      ],
    ], $taxonomy_term_infos);
  }

  /**
   * Helper function.
   *
   * @param array $paragraph_infos
   *   The paragraph infos to use.
   *
   * @return array
   *   Return common part to create paragraph.
   */
  protected function getCompleteParagraphInfos(array $paragraph_infos) {
    return array_merge([
      'type' => [
        'value' => 'es_test',
        'checker_callback' => 'getTargetId',
      ],
    ], $paragraph_infos);
  }

  /**
   * Helper function.
   *
   * @param array $block_infos
   *   The block infos to use.
   *
   * @return array
   *   Return common part to create blocks.
   */
  protected function getCompleteBlockInfos(array $block_infos) {
    return array_merge([
      'type' => [
        'value' => 'es_test',
        'checker_callback' => 'getTargetId',
      ],
      'info' => [
        'value' => $this->randomString(),
        'checker_callback' => 'getValue',
      ],
    ], $block_infos);
  }

  /**
   * Helper function.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $entity_uuid
   *   Then entity UUID.
   *
   * @return string
   *   Return the entity ID if it exists. Empty string otherwise.
   */
  protected function getEntityId($entity_type_id, $entity_uuid) {
    $existing_entity_id = '';
    $existing_entity = $this->loadEntity($entity_type_id, $entity_uuid);

    if (!is_null($existing_entity)) {
      $existing_entity_id = $existing_entity->id();
    }

    return $existing_entity_id;
  }

  /**
   * Helper function.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $entity_uuid
   *   Then entity UUID.
   *
   * @return string
   *   Return the entity revision ID if it exists. Empty string otherwise.
   */
  protected function getEntityRevisionId($entity_type_id, $entity_uuid) {
    $existing_entity_id = '';
    $existing_entity = $this->loadEntity($entity_type_id, $entity_uuid);

    if (!is_null($existing_entity) && $existing_entity instanceof RevisionableInterface) {
      $existing_entity_id = $existing_entity->getRevisionId();
    }

    return $existing_entity_id;
  }

  /**
   * Helper function.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $entity_uuid
   *   Then entity UUID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Return the entity if it exists. NULL otherwise.
   */
  protected function loadEntity($entity_type_id, $entity_uuid) {
    $existing_entity = NULL;
    $existing_entities = $this->entityTypeManager->getStorage($entity_type_id)->loadByProperties(['uuid' => $entity_uuid]);

    if (!empty($existing_entities)) {
      $existing_entity = array_shift($existing_entities);
    }

    return $existing_entity;
  }

  /**
   * Helper function.
   *
   * @param array $selected_entities
   *   An array of entities UUIDs to filter the endpoint by.
   * @param string $channel_id
   *   The channel id.
   *
   * @return string
   *   The prepared URL.
   */
  protected function prepareUrlFilteredOnUuids(array $selected_entities, $channel_id) {
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $channel_url = $channel_infos[$channel_id]['url'];
    $parsed_url = UrlHelper::parse($channel_url);
    $query = $parsed_url['query'];
    $query['filter']['uuid-filter'] = [
      'condition' => [
        'path' => 'id',
        'operator' => 'IN',
        'value' => array_values($selected_entities),
      ],
    ];
    $query = UrlHelper::buildQuery($query);
    $prepared_url = $parsed_url['path'] . '?' . $query;

    return $prepared_url;
  }

  /**
   * Helper function.
   *
   * @return array
   *   An array of data.
   */
  protected function preparePhysicalFilesAndFileEntitiesData() {
    /** @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager */
    $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager');
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');

    $files_entities_data = [];
    foreach (static::$filesData as $file_uuid => $file_data) {
      $stream_wrapper = $stream_wrapper_manager->getViaUri($file_data['uri']);
      $directory_uri = $stream_wrapper->dirname($file_data['uri']);
      $file_system->prepareDirectory($directory_uri, FileSystemInterface::CREATE_DIRECTORY);
      if (isset($file_data['file_content'])) {
        file_put_contents($file_data['uri'], $file_data['file_content']);
        $this->filesSize[$file_uuid] = filesize($file_data['uri']);
      }
      elseif (isset($file_data['file_content_callback'])) {
        $this->{$file_data['file_content_callback']}($file_uuid, $file_data);
      }

      $files_entities_data[$file_uuid] = [
        'filename' => [
          'value' => $file_data['filename'],
          'checker_callback' => 'getValue',
        ],
        'uri' => [
          'value' => $file_data['uri'],
          'checker_callback' => 'getValue',
        ],
        'filemime' => [
          'value' => $file_data['filemime'],
          'checker_callback' => 'getValue',
        ],
        'status' => [
          'value' => FILE_STATUS_PERMANENT,
          'checker_callback' => 'getValue',
        ],
      ];
    }
    return $files_entities_data;
  }

}
