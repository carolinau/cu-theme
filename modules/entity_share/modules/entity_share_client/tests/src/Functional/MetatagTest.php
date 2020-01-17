<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\node\NodeInterface;

/**
 * General functional test class for metatag field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class MetatagTest extends EntityShareClientFunctionalTestBase {

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
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          'es_test' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
            'field_es_test_metatag' => [
              'value' => serialize([
                'abstract' => 'test abstract',
              ]),
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test metatag plugin.
   *
   * Only exposed default tags settings checked.
   *
   * In the first case even if default metatags are exposed, as the exposed data
   * is only token, it is not saved back into the field.
   */
  public function testExposeDefaultTags() {
    $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
      'id' => 'node--es_test',
      'disabled' => FALSE,
      'path' => 'node/es_test',
      'resourceType' => 'node--es_test',
      'resourceFields' => [
        'field_es_test_metatag' => [
          'fieldName' => 'field_es_test_metatag',
          'publicName' => 'field_es_test_metatag',
          'enhancer' => [
            'id' => 'entity_share_metatag',
            'settings' => [
              'expose_default_tags' => TRUE,
              'replace_tokens' => FALSE,
              'clear_tokens' => FALSE,
            ],
          ],
          'disabled' => FALSE,
        ],
      ],
    ])->save();
    $this->prepareContent();
    $this->populateRequestService();
    $this->deleteContent();
    $this->pullEveryChannels();

    $node = $this->loadEntity('node', 'es_test');
    $node_metatags = unserialize($node->get('field_es_test_metatag')->getValue()[0]['value']);
    $expected_metatags = [
      'abstract' => 'test abstract',
    ];

    $this->assertEquals($expected_metatags, $node_metatags, 'The node has the expected metatags.');
  }

  /**
   * Test metatag plugin.
   *
   * Settings exposed default tags and replace tokens checked.
   *
   * In the second case, default tags ith tokens had been replaced by the real
   * values. But as for the first case, when a value is only an unreplaced
   * token, Metatag does not save back the value.
   *
   * So for example, we don't see in the result the [node:summary] token.
   */
  public function testExposeDefaultTagsAndTokenReplace() {
    $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
      'id' => 'node--es_test',
      'disabled' => FALSE,
      'path' => 'node/es_test',
      'resourceType' => 'node--es_test',
      'resourceFields' => [
        'field_es_test_metatag' => [
          'fieldName' => 'field_es_test_metatag',
          'publicName' => 'field_es_test_metatag',
          'enhancer' => [
            'id' => 'entity_share_metatag',
            'settings' => [
              'expose_default_tags' => TRUE,
              'replace_tokens' => TRUE,
              'clear_tokens' => FALSE,
            ],
          ],
          'disabled' => FALSE,
        ],
      ],
    ])->save();
    $this->prepareContent();

    $node = $this->loadEntity('node', 'es_test');
    $node_title = $node->label();
    $node_url = $node->toUrl('canonical')->setAbsolute()->toString();

    $this->populateRequestService();
    $this->deleteContent();
    $this->pullEveryChannels();

    $node = $this->loadEntity('node', 'es_test');
    $node_metatags = unserialize($node->get('field_es_test_metatag')->getValue()[0]['value']);
    $expected_metatags = [
      'canonical_url' => $node_url,
      'title' => $node_title . ' | Drupal',
      'abstract' => 'test abstract',
    ];

    $this->assertEquals($expected_metatags, $node_metatags, 'The node has the expected metatags.');
  }

  /**
   * Test metatag plugin.
   *
   * All settings checked.
   *
   * Same as the second case, the difference will be in the JSON output, the
   * [node:summary] token will be exposed but not replaced, so Metatag does not
   * save back the value.
   */
  public function testExposeDefaultTagsAndTokenReplaceAndClearToken() {
    $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
      'id' => 'node--es_test',
      'disabled' => FALSE,
      'path' => 'node/es_test',
      'resourceType' => 'node--es_test',
      'resourceFields' => [
        'field_es_test_metatag' => [
          'fieldName' => 'field_es_test_metatag',
          'publicName' => 'field_es_test_metatag',
          'enhancer' => [
            'id' => 'entity_share_metatag',
            'settings' => [
              'expose_default_tags' => TRUE,
              'replace_tokens' => TRUE,
              'clear_tokens' => TRUE,
            ],
          ],
          'disabled' => FALSE,
        ],
      ],
    ])->save();
    $this->prepareContent();

    $node = $this->loadEntity('node', 'es_test');
    $node_title = $node->label();
    $node_url = $node->toUrl('canonical')->setAbsolute()->toString();

    $this->populateRequestService();
    $this->deleteContent();
    $this->pullEveryChannels();

    $node = $this->loadEntity('node', 'es_test');
    $node_metatags = unserialize($node->get('field_es_test_metatag')->getValue()[0]['value']);
    $expected_metatags = [
      'canonical_url' => $node_url,
      'title' => $node_title . ' | Drupal',
      'abstract' => 'test abstract',
    ];

    $this->assertEquals($expected_metatags, $node_metatags, 'The node has the expected metatags.');
  }

}
