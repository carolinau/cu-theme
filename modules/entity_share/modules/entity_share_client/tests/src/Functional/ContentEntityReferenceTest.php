<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\node\NodeInterface;

/**
 * Functional test class for content entity reference field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class ContentEntityReferenceTest extends EntityShareClientFunctionalTestBase {

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
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          // Used for internal reference.
          'es_test_to_be_referenced' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          // Content reference.
          'es_test_content_reference' => $this->getCompleteNodeInfos([
            'field_es_test_content_reference' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('node', 'es_test_to_be_referenced'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedContentReferenceValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test that a reference entity value is still maintained.
   */
  public function testReferenceEntityValue() {
    $this->pullEveryChannels();
    $this->checkCreatedEntities();
  }

  /**
   * Test that a referenced entity is pulled even if not selected.
   */
  public function testReferencedEntityCreated() {
    // Select only the referencing entity.
    $selected_entities = [
      'es_test_content_reference',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->jsonapiHelper->setRemote($this->remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($this->remote);

    $response = $this->requestService->request($http_client, 'GET', $prepared_url);
    $json = Json::decode((string) $response->getBody());
    $this->jsonapiHelper->importEntityListData(EntityShareUtility::prepareData($json['data']));

    $this->checkCreatedEntities();
  }

  /**
   * Helper function.
   *
   * After the value_callback is re-evaluated, the nid will be changed. So need
   * a specific checker_callback.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   *
   * @return array
   *   The expected value after import.
   */
  protected function getExpectedContentReferenceValue(ContentEntityInterface $entity, string $field_name) {
    return [
      [
        'target_id' => $this->getEntityId('node', 'es_test_to_be_referenced'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();

    // Needs to make the requests when only the referencing content will be
    // required.
    $selected_entities = [
      'es_test_content_reference',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->jsonapiHelper->setRemote($this->remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($this->remote);

    $this->discoverJsonApiEndpoints($http_client, $prepared_url);
  }

}
