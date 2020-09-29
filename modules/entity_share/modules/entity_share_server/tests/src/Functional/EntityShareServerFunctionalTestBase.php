<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_server\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\RequestOptions;

/**
 * Base class for Entity Share Server functional tests.
 */
abstract class EntityShareServerFunctionalTestBase extends BrowserTestBase {

  use EntityShareServerRequestTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_share_server',
    'entity_share_test',
    'basic_auth',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

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
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser($this->getAdministratorPermissions());
    $this->channelUser = $this->drupalCreateUser($this->getChannelUserPermissions());
    $this->entityTypeManager = $this->container->get('entity_type.manager');
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

}
