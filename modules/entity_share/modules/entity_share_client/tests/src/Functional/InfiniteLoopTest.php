<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\node\NodeInterface;

/**
 * Functional test class for infinite loop in content entity reference field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class InfiniteLoopTest extends EntityShareClientFunctionalTestBase {

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
    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareContent() {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create two nodes referencing each other.
    $node_1 = $node_storage->create([
      'uuid' => 'es_test_content_reference_one',
      'type' => static::$entityBundleId,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
    ]);
    $node_1->save();

    $node_2 = $node_storage->create([
      'uuid' => 'es_test_content_reference_two',
      'type' => static::$entityBundleId,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      'field_es_test_content_reference' => $node_1->id(),
    ]);
    $node_2->save();

    $node_1->set('field_es_test_content_reference', $node_2->id());
    $node_1->save();

    $this->entities = [
      'node' => [
        'es_test_content_reference_one' => $node_1,
        'es_test_content_reference_two' => $node_2,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [];
  }

  /**
   * Test that a referenced entity is pulled even if not selected.
   *
   * In a scenario of infinite loop.
   */
  public function testInfiniteLoop() {
    // Select only the first referencing entity.
    $selected_entities = [
      'es_test_content_reference_one',
    ];
    $this->infiniteLoopTestHelper($selected_entities);

    // Reset before starting again.
    $this->resetImportedContent();

    // Select only the second referencing entity.
    $selected_entities = [
      'es_test_content_reference_two',
    ];
    $this->infiniteLoopTestHelper($selected_entities);
  }

  /**
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();

    // Needs to make the requests when only one referencing content will be
    // required.
    $selected_entities = [
      'es_test_content_reference_one',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($prepared_url);

    // Needs to make the requests when only one referencing content will be
    // required.
    $selected_entities = [
      'es_test_content_reference_two',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($prepared_url);
  }

  /**
   * Helper function.
   *
   * @param array $selected_entities
   *   The selected entities to pull.
   */
  protected function infiniteLoopTestHelper(array $selected_entities) {
    $this->importSelectedEntities($selected_entities);

    // Check that both entities had been created. If the process ends the
    // infinite loop has been avoided.
    $uuids = [
      'es_test_content_reference_one',
      'es_test_content_reference_two',
    ];
    foreach ($uuids as $uuid) {
      $node = $this->loadEntity('node', $uuid);
      $this->assertTrue($node instanceof NodeInterface, 'The node with the uuid ' . $uuid . ' has been recreated.');
    }
  }

}
