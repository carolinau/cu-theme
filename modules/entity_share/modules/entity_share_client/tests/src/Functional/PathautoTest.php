<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\entity_share\Plugin\jsonapi\FieldEnhancer\EntitySharePathautoEnhancer;
use Drupal\node\NodeInterface;

/**
 * General functional test class for path field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class PathautoTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'jsonapi_extras',
    'pathauto',
  ];

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
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          'es_test_path_auto' => $this->getCompleteNodeInfos([
            'title' => [
              'value' => 'Automatic',
              'checker_callback' => 'getValue',
            ],
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          'es_test_path_manual' => $this->getCompleteNodeInfos([
            'title' => [
              'value' => 'Manual',
              'checker_callback' => 'getValue',
            ],
            'path' => [
              'value' => [
                [
                  'alias' => '/manual_path',
                  'pathauto' => 0,
                ],
              ],
              'checker_callback' => 'getValue',
            ],
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
   * Test pathauto plugin.
   *
   * In the first case current pathauto state is exposed.
   */
  public function testExposeCurrentState() {
    $this->pathautoTestSetup(EntitySharePathautoEnhancer::EXPOSE_CURRENT_PATHAUTO);
    $this->assetEntityPath('es_test_path_auto', '/client/automatic', 'As the pathauto state is preserved, the client website has generated an alias based on its own pathauto pattern.');
    $this->assetEntityPath('es_test_path_manual', '/manual_path', 'As the pathauto state is preserved, the client website has not generated an alias and has used the one provided by the server website.');
  }

  /**
   * Test pathauto plugin.
   *
   * In the second case, pathauto state is forced to enabled.
   */
  public function testForceEnable() {
    $this->pathautoTestSetup(EntitySharePathautoEnhancer::FORCE_ENABLE_PATHAUTO);
    $this->assetEntityPath('es_test_path_auto', '/client/automatic', 'As the pathauto state is forced to be on, the client website has generated an alias based on its own pathauto pattern.');
    $this->assetEntityPath('es_test_path_manual', '/client/manual', 'As the pathauto state is forced to be on, the client website has generated an alias based on its own pathauto pattern.');
  }

  /**
   * Test pathauto plugin.
   *
   * In the third case, pathauto state is forced to disabled.
   */
  public function testForceDisable() {
    $this->pathautoTestSetup(EntitySharePathautoEnhancer::FORCE_DISABLE_PATHAUTO);
    $this->assetEntityPath('es_test_path_auto', '/server/automatic', 'As the pathauto state is forced to be off, the client website has not generated an alias and has used the one provided by the server (automatically created on the server website).');
    $this->assetEntityPath('es_test_path_manual', '/manual_path', 'As the pathauto state is forced to be off, the client website has not generated an alias and has used the one provided by the server (manually created on the server website).');
  }

  /**
   * {@inheritdoc}
   */
  protected function deleteContent() {
    parent::deleteContent();
    $pathauto_patterns = $this->entityTypeManager->getStorage('pathauto_pattern')
      ->loadMultiple();

    foreach ($pathauto_patterns as $pathauto_pattern) {
      $pathauto_pattern->delete();
    }
  }

  /**
   * Helper function.
   *
   * @param string $behavior
   *   The behavior of the pathauto field enahancer plugin.
   */
  protected function pathautoTestSetup($behavior) {
    $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
      'id' => 'node--es_test',
      'disabled' => FALSE,
      'path' => 'node/es_test',
      'resourceType' => 'node--es_test',
      'resourceFields' => [
        'path' => [
          'fieldName' => 'path',
          'publicName' => 'path',
          'enhancer' => [
            'id' => 'entity_share_pathauto',
            'settings' => [
              'behavior' => $behavior,
            ],
          ],
          'disabled' => FALSE,
        ],
      ],
    ])->save();

    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $pathauto_pattern_storage */
    $pathauto_pattern_storage = $this->entityTypeManager->getStorage('pathauto_pattern');

    /** @var \Drupal\pathauto\PathautoPatternInterface $pattern */
    $pattern = $pathauto_pattern_storage->create([
      'id' => 'server',
      'label' => 'Test',
      'type' => 'canonical_entities:node',
      'pattern' => 'server/[node:title]',
    ]);
    $pattern->save();
    $this->prepareContent();
    $this->populateRequestService();
    $this->deleteContent();
    /** @var \Drupal\pathauto\PathautoPatternInterface $pattern */
    $pattern = $pathauto_pattern_storage->create([
      'id' => 'client',
      'label' => 'Test',
      'type' => 'canonical_entities:node',
      'pattern' => 'client/[node:title]',
    ]);
    $pattern->save();
    $this->pullEveryChannels();
  }

  /**
   * Helper function to test an entity path.
   *
   * @param string $entity_uuid
   *   The entity UUID.
   * @param string $expected_path
   *   The expected path.
   * @param string $message
   *   The message.
   */
  protected function assetEntityPath($entity_uuid, $expected_path, $message = '') {
    $path_auto_node = $this->loadEntity('node', $entity_uuid);
    $path = $path_auto_node->get('path')->getValue();
    $this->assertEquals($expected_path, $path[0]['alias'], $message);
  }

}
