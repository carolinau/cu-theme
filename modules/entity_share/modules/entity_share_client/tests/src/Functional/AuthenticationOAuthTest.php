<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\consumers\Entity\Consumer;
use Drupal\Core\Site\Settings;
use Drupal\entity_share_client\Entity\RemoteInterface;
use Drupal\Tests\simple_oauth\Functional\SimpleOauthTestTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use GuzzleHttp\RequestOptions;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Functional test class for import with "OAuth" authorization.
 *
 * @group entity_share
 * @group entity_share_client
 */
class AuthenticationOAuthTest extends AuthenticationTestBase {

  use SimpleOauthTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'serialization',
    'simple_oauth',
  ];

  /**
   * Injected key service.
   *
   * @var \Drupal\entity_share_client\Service\KeyProvider
   */
  protected $keyService;

  /**
   * The client secret.
   *
   * @var string
   */
  protected $clientSecret;

  /**
   * The client (consumer) entities, one per each user.
   *
   * @var \Drupal\consumers\Entity\Consumer[]
   */
  protected $clients;

  /**
   * User role with OAuth permissions and unrestricted node access.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected $clientRole;

  /**
   * User role with OAuth permissions.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected $clientRolePlain;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->keyService = $this->container->get('entity_share_client.key_provider');

    // Give admin user access to all channels (channel user already has it).
    foreach ($this->channels as $channel) {
      $authorized_users = $channel->get('authorized_users');
      $authorized_users = array_merge($authorized_users, [$this->adminUser->uuid()]);
      $channel->set('authorized_users', $authorized_users);
      $channel->save();
    }

    // Create Keys with users' credentials.
    $this->createKey($this->adminUser);
    $this->createKey($this->channelUser);

    // Change the initial remote configuration: it will use the admin user
    // to authenticate. We first test as administrative user because they have
    // access to all nodes, so we can in the beginning of the test pull the
    // channel and use `checkCreatedEntities()`.
    $plugin = $this->createAuthenticationPlugin($this->adminUser, $this->remote);
    $this->remote->mergePluginConfig($plugin);
    $this->remote->save();

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function createAuthenticationPlugin(UserInterface $user, RemoteInterface $remote) {
    // Create all needed OAuth-related entities on the "server" side.
    $this->serverOauthSetup();

    $plugin = $this->authPluginManager->createInstance('oauth');
    $configuration = $plugin->getConfiguration();

    // To properly test, delete the cached key used in the previous run.
    if ($this->keyValueStore->get($configuration['uuid'] . '-' . $plugin->getPluginId()) instanceof AccessTokenInterface) {
      $this->keyValueStore->delete($configuration['uuid'] . '-' . $plugin->getPluginId());
    }

    // Override Guzzle HTTP client options.
    // This is mandatory because otherwise in testing environment there would
    // be a redirection from POST /oauth/token to GET /oauth/token.
    // @see GuzzleHttp\RedirectMiddleware::modifyRequest().
    $request_options = [
      RequestOptions::HTTP_ERRORS => FALSE,
      RequestOptions::ALLOW_REDIRECTS => [
        'strict' => TRUE,
      ],
    ];
    $site_settings = Settings::getAll();
    $site_settings['http_client_config'] = $request_options;
    new Settings($site_settings);

    // Obtain the access token from server.
    $credentials = [
      'username' => $user->getAccountName(),
      'password' => $user->passRaw,
      'client_id' => $this->clients[$user->id()]->uuid(),
      'client_secret' => $this->clientSecret,
      'authorization_path' => '/oauth/authorize',
      'token_path' => '/oauth/token',
    ];

    $access_token = '';
    try {
      $access_token = $plugin->initalizeToken($remote, $credentials);
    }
    catch (\Exception $e) {
      // Do nothing.
    }
    // Since this is an important part of OAuth functionality,
    // assert that it is successful.
    $this->assertNotEmpty($access_token, 'The access token is not empty.');

    // Remove the username and password.
    unset($credentials['username']);
    unset($credentials['password']);
    $storage_key = $configuration['uuid'];
    $this->keyValueStore->set($storage_key, $credentials);
    // Save the token.
    $this->keyValueStore->set($storage_key . '-' . $plugin->getPluginId(), $access_token);

    // We are using key value store for local credentials storage.
    $configuration['data'] = [
      'credential_provider' => 'entity_share',
      'storage_key' => $storage_key,
    ];
    $plugin->setConfiguration($configuration);

    return $plugin;
  }

  /**
   * Test that correct entities are created with different authentications.
   */
  public function testImport() {
    // 1. Test content creation as administrative
    // user: both published and unpublished nodes should be created.
    // In this run we are also testing the access to private physical files.
    // First, assert that files didn't exist before import.
    foreach (static::$filesData as $file_data) {
      $this->assertFalse(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' has been deleted.');
    }

    // Pull channel and test that all nodes and file entities are there.
    $this->pullChannel('node_es_test_en');
    $this->checkCreatedEntities();

    // Some stronger assertions for the uploaded private file.
    foreach (static::$filesData as $file_definition) {
      $this->assertTrue(file_exists($file_definition['uri']), 'The physical file ' . $file_definition['filename'] . ' has been pulled and recreated.');
      $this->assertEqual(file_get_contents($file_definition['uri']), $file_definition['file_content'], 'The content of physical file ' . $file_definition['filename'] . ' is correct.');
    }

    // 2. Test as a non-administrative user who can't access unpublished nodes.
    // Change the remote so that is uses the channel user's credentials.
    $plugin = $this->createAuthenticationPlugin($this->channelUser, $this->remote);
    $this->remote->mergePluginConfig($plugin);
    $this->remote->save();

    // Delete all "client" entities created after the first import.
    $this->resetImportedContent();
    // Also clean up all uploaded files.
    foreach (static::$filesData as $file_data) {
      $this->fileSystem->delete($file_data['uri']);
    }
    // There is no need to test the physical files anymore, so we will remove
    // them from the entity array.
    unset($this->entitiesData['file']);
    unset($this->entitiesData['node']['en']['es_test_node_import_published']['field_es_test_file']);

    // Since the remote ID remains the same, we need to reset some of
    // remote manager's cached values.
    $this->resetRemoteCaches();
    // Prepare the "server" content again.
    $this->prepareContent();

    // Get channel info so that individual channels can be pulled next.
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);

    // Re-import data from JSON:API.
    $this->reimportChannel($channel_infos);

    // Assertions.
    $entity_storage = $this->entityTypeManager->getStorage('node');

    $published = $entity_storage->loadByProperties(['uuid' => 'es_test_node_import_published']);
    $this->assertEqual(count($published), 1, 'The published node was imported.');

    $not_published = $entity_storage->loadByProperties(['uuid' => 'es_test_node_import_not_published']);
    $this->assertEqual(count($not_published), 0, 'The unpublished node was not imported.');

    // 3. Test as non-administrative user, but with credentials stored using
    // Key module.
    $this->setupAuthorizationPluginWithKey($this->channelUser);

    $this->resetImportedContent();
    $this->resetRemoteCaches();
    $this->prepareContent();

    $this->reimportChannel($channel_infos);

    // Assertions.
    $entity_storage = $this->entityTypeManager->getStorage('node');

    $published = $entity_storage->loadByProperties(['uuid' => 'es_test_node_import_published']);
    $this->assertEqual(count($published), 1, 'The published node was imported.');

    $not_published = $entity_storage->loadByProperties(['uuid' => 'es_test_node_import_not_published']);
    $this->assertEqual(count($not_published), 0, 'The unpublished node was not imported.');
  }

  /**
   * Helper function: updates the existing OAuth plugin to use Key storage.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user whose credentials will be used for the plugin.
   */
  private function setupAuthorizationPluginWithKey(UserInterface $account) {
    $plugin = $this->remote->getAuthPlugin();
    $configuration = $plugin->getConfiguration();

    // To properly test, delete the cached key used in the previous run.
    if ($this->keyValueStore->get($configuration['uuid'] . '-' . $plugin->getPluginId()) instanceof AccessTokenInterface) {
      $this->keyValueStore->delete($configuration['uuid'] . '-' . $plugin->getPluginId());
    }

    // Obtain the access token from server again, but now we are using the
    // credentials saved in the Key.
    $credentials = $this->keyService->getCredentials($plugin);
    $credentials['username'] = $account->getAccountName();
    $credentials['password'] = $account->passRaw;
    $request_options = [
      RequestOptions::HTTP_ERRORS => FALSE,
      RequestOptions::ALLOW_REDIRECTS => [
        'strict' => TRUE,
      ],
    ];
    $access_token = '';
    try {
      $access_token = $plugin->initalizeToken($this->remote, $credentials, $request_options);
    }
    catch (\Exception $e) {
      // Do nothing.
    }
    // Since this is an important part of OAuth functionality,
    // assert that it is successful.
    $this->assertNotEmpty($access_token, 'The new access token is not empty.');
    // Save the obtained key.
    $this->keyValueStore->set($configuration['uuid'] . '-' . $plugin->getPluginId(), $access_token);

    // Save the new configuration of the plugin.
    $configuration['data'] = [
      'credential_provider' => 'key',
      'storage_key' => 'key_oauth_' . $account->id(),
    ];
    $plugin->setConfiguration($configuration);

    // Save the "Remote" config entity.
    $this->remote->mergePluginConfig($plugin);
    $this->remote->save();
  }

  /**
   * Helper function: creates needed server-side entities needed for OAuth.
   */
  private function serverOauthSetup() {
    // Create OAuth roles and assign these roles to users.
    $this->clientRole = Role::create([
      'id' => $this->getRandomGenerator()->name(8, TRUE),
      'label' => $this->getRandomGenerator()->word(5),
      'is_admin' => FALSE,
    ]);
    $this->clientRole->grantPermission('grant simple_oauth codes');
    $this->clientRole->grantPermission('entity_share_server_access_channels');
    $this->clientRole->grantPermission('bypass node access');
    $this->clientRole->save();
    $this->adminUser->addRole($this->clientRole->id());

    $this->clientRolePlain = Role::create([
      'id' => $this->getRandomGenerator()->name(8, TRUE),
      'label' => $this->getRandomGenerator()->word(5),
      'is_admin' => FALSE,
    ]);
    $this->clientRolePlain->grantPermission('grant simple_oauth codes');
    $this->clientRolePlain->grantPermission('entity_share_server_access_channels');
    $this->clientRolePlain->save();
    $this->channelUser->addRole($this->clientRolePlain->id());

    // Create client secret.
    $this->clientSecret = $this->getRandomGenerator()->string();

    // Create OAuth consumers.
    $this->createOauthConsumer($this->adminUser, $this->clientRole);
    $this->createOauthConsumer($this->channelUser, $this->clientRolePlain);

    // Create private and public keys for the OAuth module.
    // Not to be confused with Key module's storage of credentials.
    $this->setUpKeys();
  }

  /**
   * Create a service consumer for OAuth.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user whose credentials will be used for the plugin.
   * @param \Drupal\user\RoleInterface $role
   *   The user role for OAuth consumer.
   */
  protected function createOauthConsumer(UserInterface $account, RoleInterface $role) {
    // Create a Consumer.
    $client = Consumer::create([
      'owner_id' => '',
      'user_id' => $account->id(),
      'label' => $this->getRandomGenerator()->name(),
      'secret' => $this->clientSecret,
      'confidential' => FALSE,
      'third_party' => TRUE,
      'roles' => [
        ['target_id' => $role->id()],
      ],
    ]);
    $client->save();
    $this->clients[$account->id()] = $client;
  }

  /**
   * Create a key of OAuth type.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user whose credentials will be used for the plugin.
   */
  protected function createKey(UserInterface $account) {
    $this->createTestKey('key_oauth_' . $account->id(), 'entity_share_oauth', 'config');
    $credentials = [
      'client_id' => $this->clients[$account->id()]->uuid(),
      'client_secret' => $this->clientSecret,
      'authorization_path' => '/oauth/authorize',
      'token_path' => '/oauth/token',
    ];
    $output = '';
    foreach ($credentials as $name => $value) {
      $output .= "\"$name\": \"$value\"\n";
    }
    $key_value = <<<EOT
{
  $output}
EOT;
    $this->testKey->setKeyValue($key_value);
    $this->testKey->save();
  }

}
