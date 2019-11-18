<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\user\UserInterface;

/**
 * Functional test class for taxonomy entity reference field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class TaxonomyEntityReferenceTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'taxonomy_term';

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
      'taxonomy_term' => [
        'en' => [
          'parent_tag' => $this->getCompleteTaxonomyTermInfos([]),
          'child_tag' => $this->getCompleteTaxonomyTermInfos([
            'parent' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('taxonomy_term', 'parent_tag'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedTaxonomyParentReferenceValue',
            ],
          ]),
        ],
      ],
      'node' => [
        'en' => [
          'es_test_taxonomy_reference' => $this->getCompleteNodeInfos([
            'type' => [
              'value' => 'es_test',
              'checker_callback' => 'getTargetId',
            ],
            'field_es_test_taxonomy' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('taxonomy_term', 'child_tag'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedTaxonomyReferenceValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test that a referenced entity is pulled even if not selected.
   *
   * This test that:
   *   - an taxonomy entity reference field is working
   *   - the parent base field on taxonomy entities is working.
   */
  public function testReferencedEntityCreated() {
    // Select only the referencing node entity.
    $selected_entities = [
      'es_test_taxonomy_reference',
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
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();

    $this->jsonapiHelper->setRemote($this->remote);
    $http_client = $this->remoteManager->prepareJsonApiClient($this->remote);

    // Needs to make the requests when only the referencing content will be
    // required.
    $selected_entities = [
      'es_test_taxonomy_reference',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($http_client, $prepared_url);
  }

  /**
   * {@inheritdoc}
   */
  protected function createChannel(UserInterface $user) {
    parent::createChannel($user);

    // Add a channel for the node.
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => 'node_es_test_en',
      'label' => $this->randomString(),
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => static::$entityLangcode,
      'authorized_users' => [
        $user->uuid(),
      ],
    ]);
    $channel->save();
    $this->channels[$channel->id()] = $channel;
  }

  /**
   * Helper function.
   *
   * After the value_callback is re-evaluated, the entity id will be changed.
   * So need a specific checker_callback.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   *
   * @return array
   *   The expected value after import.
   */
  protected function getExpectedTaxonomyReferenceValue(ContentEntityInterface $entity, string $field_name) {
    return [
      [
        'target_id' => $this->getEntityId('taxonomy_term', 'child_tag'),
      ],
    ];
  }

  /**
   * Helper function.
   *
   * After the value_callback is re-evaluated, the entity id will be changed.
   * So need a specific checker_callback.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   *
   * @return array
   *   The expected value after import.
   */
  protected function getExpectedTaxonomyParentReferenceValue(ContentEntityInterface $entity, string $field_name) {
    return [
      [
        'target_id' => $this->getEntityId('taxonomy_term', 'parent_tag'),
      ],
    ];
  }

}
