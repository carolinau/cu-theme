<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\node\NodeInterface;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Functional test class for media entity reference field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class MediaEntityReferenceTest extends EntityShareClientFunctionalTestBase {
  use TestFileCreationTrait;

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
   * An array of data to generate physical files.
   *
   * @var array
   */
  protected static $filesData = [
    'file_audio' => [
      'filename' => 'sample.mp3',
      'filemime' => 'audio/mpeg',
      'uri' => 'public://sample.mp3',
      'file_content_callback' => 'getMediaEntityReferenceTestFiles',
    ],
    'file_document' => [
      'filename' => 'sample.pdf',
      'filemime' => 'application/pdf',
      'uri' => 'public://sample.pdf',
      'file_content_callback' => 'getMediaEntityReferenceTestFiles',
    ],
    'file_image' => [
      'filename' => 'image-test.jpg',
      'filemime' => 'image/jpeg',
      'uri' => 'public://image-test.jpg',
    ],
    'file_video' => [
      'filename' => 'sample.mp4',
      'filemime' => 'video/mp4',
      'uri' => 'public://sample.mp4',
      'file_content_callback' => 'getMediaEntityReferenceTestFiles',
    ],
  ];

  /**
   * An array of file size keyed by file UUID.
   *
   * @var array
   */
  protected $filesSize = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->getTestFiles('image');
    // Special case for the image created using native helper method.
    if (isset(static::$filesData['file_image'])) {
      $this->filesSize['file_image'] = filesize(static::$filesData['file_image']['uri']);
    }

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getImportConfigProcessorSettings() {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['physical_file'] = [
      'weights' => [
        'process_entity' => 0,
      ],
    ];
    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'file' => [
        'en' => $this->preparePhysicalFilesAndFileEntitiesData(),
      ],
      'media' => [
        'en' => [
          'es_test_audio' => $this->getCompleteMediaInfos([
            'field_es_test_audio_file' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'file_audio'),
                  ],
                ];
              },
              'checker_callback' => 'getFilteredStructureValues',
            ],
            'bundle' => [
              'value' => 'es_test_audio',
              'checker_callback' => 'getTargetId',
            ],
          ]),
          'es_test_document' => $this->getCompleteMediaInfos([
            'field_es_test_document' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'file_document'),
                    'display' => 1,
                  ],
                ];
              },
              'checker_callback' => 'getFilteredStructureValues',
            ],
            'bundle' => [
              'value' => 'es_test_document',
              'checker_callback' => 'getTargetId',
            ],
          ]),
          'es_test_image' => $this->getCompleteMediaInfos([
            'field_es_test_image' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'file_image'),
                    // Can't use faker because it is a value_callback to handle
                    // the target_id.
                    'alt' => 'Alt text',
                  ],
                ];
              },
              'checker_callback' => 'getFilteredStructureValues',
            ],
            'bundle' => [
              'value' => 'es_test_image',
              'checker_callback' => 'getTargetId',
            ],
          ]),
          'es_test_remote_video' => $this->getCompleteMediaInfos([
            'field_es_test_oembed_video' => [
              'value' => 'https://www.youtube.com/watch?v=Apqd4ff0NRI',
              'checker_callback' => 'getValue',
            ],
            'bundle' => [
              'value' => 'es_test_remote_video',
              'checker_callback' => 'getTargetId',
            ],
          ]),
          'es_test_video' => $this->getCompleteMediaInfos([
            'field_es_test_video_file' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'file_video'),
                  ],
                ];
              },
              'checker_callback' => 'getFilteredStructureValues',
            ],
            'bundle' => [
              'value' => 'es_test_video',
              'checker_callback' => 'getTargetId',
            ],
          ]),
        ],
      ],
      'node' => [
        'en' => [
          'es_test_media' => $this->getCompleteNodeInfos([
            'field_es_test_media' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('media', 'es_test_audio'),
                  ],
                  [
                    'target_id' => $this->getEntityId('media', 'es_test_document'),
                  ],
                  [
                    'target_id' => $this->getEntityId('media', 'es_test_image'),
                  ],
                  [
                    'target_id' => $this->getEntityId('media', 'es_test_remote_video'),
                  ],
                  [
                    'target_id' => $this->getEntityId('media', 'es_test_video'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedMediaReferenceValue',
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
   * Test basic pull feature.
   */
  public function testBasicPull() {
    foreach (static::$filesData as $file_data) {
      $this->assertFalse(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' has been deleted.');
    }

    $this->pullEveryChannels();
    $this->checkCreatedEntities();

    foreach (static::$filesData as $file_uuid => $file_data) {
      $this->assertTrue(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' has been pulled and recreated.');
      if (isset($file_data['file_content'])) {
        $recreated_file_data = file_get_contents($file_data['uri']);
        $this->assertEquals($file_data['file_content'], $recreated_file_data, 'The recreated physical file ' . $file_data['filename'] . ' has the same content.');
      }

      if (isset($this->filesSize[$file_uuid])) {
        $this->assertEquals($this->filesSize[$file_uuid], filesize($file_data['uri']), 'The recreated physical file ' . $file_data['filename'] . ' has the same size has the original.');
      }
    }
  }

  /**
   * Helper function.
   *
   * After the value_callback is re-evaluated, the mid will be changed. So need
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
  protected function getExpectedMediaReferenceValue(ContentEntityInterface $entity, string $field_name) {
    return [
      [
        'target_id' => $this->getEntityId('media', 'es_test_audio'),
      ],
      [
        'target_id' => $this->getEntityId('media', 'es_test_document'),
      ],
      [
        'target_id' => $this->getEntityId('media', 'es_test_image'),
      ],
      [
        'target_id' => $this->getEntityId('media', 'es_test_remote_video'),
      ],
      [
        'target_id' => $this->getEntityId('media', 'es_test_video'),
      ],
    ];
  }

  /**
   * Helper function.
   *
   * @param string $file_uuid
   *   The file UUID.
   * @param array $file_data
   *   The file data as in static::filesData.
   */
  protected function getMediaEntityReferenceTestFiles($file_uuid, array $file_data) {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $filepath = drupal_get_path('module', 'entity_share') . '/tests/fixtures/files/' . $file_data['filename'];
    $file_system->copy($filepath, PublicStream::basePath());
    $this->filesSize[$file_uuid] = filesize($filepath);
  }

}
