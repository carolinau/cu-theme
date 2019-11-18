<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests remote url.
 *
 * @group entity_share
 * @group entity_share_client
 */
class RemoteUrlTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'serialization',
    'jsonapi',
    'entity_share_client',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('remote');
  }

  /**
   * Tests that trailing slash is removed from url.
   */
  public function testRemotePreSave() {
    $remote_storage = $this->entityTypeManager->getStorage('remote');
    $remote = $remote_storage->create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'url' => 'http://example.com',
      'basic_auth_username' => 'test',
      'basic_auth_password' => 'test',
    ]);
    $remote->save();
    $this->assertEqual($remote->get('url'), 'http://example.com');

    $remote->set('url', 'http://example.com/');
    $remote->save();
    $this->assertEqual($remote->get('url'), 'http://example.com');

    $remote->set('url', 'http://example.com/subdirectory');
    $remote->save();
    $this->assertEqual($remote->get('url'), 'http://example.com/subdirectory');

    $remote->set('url', 'http://example.com/subdirectory/');
    $remote->save();
    $this->assertEqual($remote->get('url'), 'http://example.com/subdirectory');
  }

}
