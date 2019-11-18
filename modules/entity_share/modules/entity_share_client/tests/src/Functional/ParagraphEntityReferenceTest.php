<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Functional test class for paragraph entity reference field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class ParagraphEntityReferenceTest extends EntityShareClientFunctionalTestBase {

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
      'paragraph' => [
        'en' => [
          'es_test_paragraph' => $this->getCompleteParagraphInfos([
            'field_es_test_text_plain' => [
              'value' => $this->faker->text(255),
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
      'node' => [
        'en' => [
          // Paragraph reference.
          'es_test_paragraph_reference' => $this->getCompleteNodeInfos([
            'field_es_test_paragraphs' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('paragraph', 'es_test_paragraph'),
                    'target_revision_id' => $this->getEntityRevisionId('paragraph', 'es_test_paragraph'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedParagraphReferenceValue',
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
  protected function getExpectedParagraphReferenceValue(ContentEntityInterface $entity, string $field_name) {
    return [
      [
        'target_id' => $this->getEntityId('paragraph', 'es_test_paragraph'),
        'target_revision_id' => $this->getEntityRevisionId('paragraph', 'es_test_paragraph'),
      ],
    ];
  }

}
