<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Url;

/**
 * Functional test class for block field.
 *
 * Dedicated test class because of the setup.
 *
 * @group entity_share
 * @group entity_share_client
 */
class BlockFieldTest extends EntityShareClientFunctionalTestBase {

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
        'field_es_test_block' => [
          'fieldName' => 'field_es_test_block',
          'publicName' => 'field_es_test_block',
          'enhancer' => [
            'id' => 'entity_share_block_field',
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
      'block_content' => [
        'en' => [
          'block_content_test' => $this->getCompleteBlockInfos([]),
        ],
      ],
      'node' => [
        'en' => [
          'es_test_block' => $this->getCompleteNodeInfos([
            'field_es_test_block' => [
              'value_callback' => function () {
                return [
                  [
                    'plugin_id' => 'system_powered_by_block',
                    'settings' => [
                      'id' => 'system_powered_by_block',
                      'label' => 'Powered by Drupal',
                      'provider' => 'system',
                      'label_display' => 'visible',
                    ],
                  ],
                  [
                    'plugin_id' => 'block_content:block_content_test',
                    'settings' => [
                      'id' => 'block_content:block_content_test',
                      'label' => 'Test',
                      'provider' => 'block_content',
                      'label_display' => 'visible',
                      'status' => TRUE,
                      'info' => '',
                      'view_mode' => 'full',
                    ],
                  ],
                ];
              },
              'checker_callback' => 'getFilteredStructureValues',
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
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();

    // Request the /jsonapi/block_content/es_test/block_content_test URL.
    $route_name = sprintf('jsonapi.%s--%s.individual', 'block_content', 'es_test');
    $url = Url::fromRoute($route_name, [
      'entity' => 'block_content_test',
    ])
      ->setOption('language', $this->container->get('language_manager')->getLanguage('en'))
      ->setOption('absolute', TRUE);
    $http_client = $this->remoteManager->prepareJsonApiClient($this->remote);
    $this->requestService->request($http_client, 'GET', $url->toString());
  }

}
