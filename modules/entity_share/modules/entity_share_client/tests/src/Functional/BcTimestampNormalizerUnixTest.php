<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

/**
 * Test class for changed behavior in core regarding timestamp normalization.
 *
 * @group entity_share
 * @group entity_share_client
 *
 * @see https://www.drupal.org/node/2982678
 * @see https://www.drupal.org/node/2859657
 * @see https://www.drupal.org/project/entity_share/issues/3059358
 */
class BcTimestampNormalizerUnixTest extends EntityShareClientFunctionalTestBase {

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

    $serialization_settings = $this->config('serialization.settings');
    $serialization_settings->set('bc_timestamp_normalizer_unix', TRUE)
      ->save(TRUE);

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          // Default.
          'es_test' => $this->getCompleteNodeInfos([
            'created' => [
              'value' => $this->faker->unixTime(),
              'checker_callback' => 'getValue',
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

}
