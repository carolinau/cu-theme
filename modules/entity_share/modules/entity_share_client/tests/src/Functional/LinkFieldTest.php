<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Functional test class for link field.
 *
 * Dedicated test class because of the setup.
 *
 * @group entity_share
 * @group entity_share_client
 */
class LinkFieldTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'jsonapi_extras',
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
  protected function setUp() {
    parent::setUp();

    $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
      'id' => 'node--es_test',
      'disabled' => FALSE,
      'path' => 'node/es_test',
      'resourceType' => 'node--es_test',
      'resourceFields' => [
        'field_es_test_link' => [
          'fieldName' => 'field_es_test_link',
          'publicName' => 'field_es_test_link',
          'enhancer' => [
            'id' => 'uuid_link',
          ],
          'disabled' => FALSE,
        ],
      ],
    ])->save();

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          // Used for internal linked.
          'es_test' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          // Link: external.
          'es_test_link_external' => $this->getCompleteNodeInfos([
            'field_es_test_link' => [
              'value' => [
                [
                  'uri' => $this->faker->url,
                  'title' => $this->faker->text(255),
                ],
              ],
              'checker_callback' => 'getFilteredStructureValues',
            ],
          ]),
          // Link: external without text.
          'es_test_link_external_without_text' => $this->getCompleteNodeInfos([
            'field_es_test_link' => [
              'value' => [
                [
                  'uri' => $this->faker->url,
                ],
              ],
              'checker_callback' => 'getFilteredStructureValues',
            ],
          ]),
          // Link: external with options.
          'es_test_link_external_with_options' => $this->getCompleteNodeInfos([
            'field_es_test_link' => [
              'value' => [
                [
                  'uri' => $this->faker->url,
                  'title' => $this->faker->text(255),
                  'options' => [
                    'attributes' => [
                      'class' => [
                        $this->faker->text(20),
                        $this->faker->text(20),
                        $this->faker->text(20),
                      ],
                    ],
                  ],
                ],
              ],
              'checker_callback' => 'getFilteredStructureValues',
            ],
          ]),
          // Link: internal.
          'es_test_link_internal' => $this->getCompleteNodeInfos([
            'field_es_test_link' => [
              'value_callback' => function () {
                return [
                  [
                    'uri' => 'entity:node/' . $this->getEntityId('node', 'es_test'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedInternalLinkValue',
            ],
          ]),
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

  /**
   * Helper function.
   *
   * After the value_callback is re-evaluated, the nid will be changed. So need
   * a specific checker_callback.
   *
   * After recreation, the node wih UUID es_test will have nid 6.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   *
   * @return array
   *   The expected value after import.
   */
  protected function getExpectedInternalLinkValue(ContentEntityInterface $entity, string $field_name) {
    return [
      [
        'uri' => 'entity:node/6',
      ],
    ];
  }

}
