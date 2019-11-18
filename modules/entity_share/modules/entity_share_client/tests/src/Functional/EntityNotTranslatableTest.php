<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Language\LanguageInterface;

/**
 * Test class for untranslatable entities.
 *
 * @group entity_share
 * @group entity_share_client
 */
class EntityNotTranslatableTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_share_entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'entity_test_not_translatable';

  /**
   * {@inheritdoc}
   */
  protected static $entityBundleId = 'entity_test_not_translatable';

  /**
   * {@inheritdoc}
   */
  protected static $entityLangcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getChannelUserPermissions() {
    $permissions = parent::getChannelUserPermissions();
    $permissions[] = 'view test entity';
    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'entity_test_not_translatable' => [
        LanguageInterface::LANGCODE_NOT_SPECIFIED => [
          'entity_test_not_translatable' => [
            'name' => [
              'value' => $this->randomString(),
              'checker_callback' => 'getValue',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Test basic pull feature.
   */
  public function testBasicPull() {
    $this->pullEveryChannels();
    $this->checkCreatedEntities();
  }

}
