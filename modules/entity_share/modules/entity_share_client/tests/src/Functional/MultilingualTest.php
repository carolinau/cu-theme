<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\entity_share_client\Service\StateInformationInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * General functional test class for multilingual scenarios.
 *
 * @group entity_share
 * @group entity_share_client
 */
class MultilingualTest extends EntityShareClientFunctionalTestBase {

  /**
   * The state information service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $stateInformation;

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

    $this->stateInformation = $this->container->get('entity_share_client.state_information');

    $this->postSetupFixture();
  }

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
          ]),
        ],
        'fr' => [
          'es_test' => $this->getCompleteNodeInfos([
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
   *
   * Test that it is possible to pull the same entity in several languages
   * during the same process.
   */
  public function testBasicPull() {
    $this->pullEveryChannels();
    $this->checkCreatedEntities();
  }

  /**
   * Test pulling content in its default translation first.
   */
  public function testDefaultTranslationFirstPull() {
    $this->pullChannel('node_es_test_en');
    $this->pullChannel('node_es_test_fr');
    $this->checkCreatedEntities();

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->loadEntity('node', 'es_test');
    $node_translation = $node->getTranslation('en');
    $this->assertTrue($node_translation->isDefaultTranslation(), 'The node default translation is the same as the initial one as it had been pulled in its default language first.');
  }

  /**
   * Test pulling content NOT in its default translation first.
   */
  public function testNonDefaultTranslationFirstPull() {
    $this->pullChannel('node_es_test_fr');
    $this->pullChannel('node_es_test_en');
    $this->checkCreatedEntities();

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->loadEntity('node', 'es_test');
    $node_translation = $node->getTranslation('fr');
    $this->assertTrue($node_translation->isDefaultTranslation(), 'The node default translation has changed as it had been pulled in another language first.');
  }

  /**
   * Test state information.
   */
  public function testComparison() {
    // 1: No import: en and fr channels data should indicate a new entity.
    $this->expectedState(StateInformationInterface::INFO_ID_NEW, StateInformationInterface::INFO_ID_NEW);

    // 2: Import entity in en: en should indicate synchronized and fr should
    // indicate a new translation.
    $this->pullChannel('node_es_test_en');
    $this->jsonapiHelper->clearImportedEntities();
    $this->expectedState(StateInformationInterface::INFO_ID_SYNCHRONIZED, StateInformationInterface::INFO_ID_NEW_TRANSLATION);

    // 3: Import entity in fr: en and fr should indicate synchronized.
    $this->pullChannel('node_es_test_fr');
    $this->jsonapiHelper->clearImportedEntities();
    $this->expectedState(StateInformationInterface::INFO_ID_SYNCHRONIZED, StateInformationInterface::INFO_ID_SYNCHRONIZED);

    // 4: Edit the en translation (also this emulates a change on the client
    // website): en should indicate changed and fr should indicate synchronized.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->loadEntity('node', 'es_test');
    $node_translation = $node->getTranslation('en');
    $node_translation->setChangedTime($this->faker->unixTime());
    $node_translation->save();
    $this->expectedState(StateInformationInterface::INFO_ID_CHANGED, StateInformationInterface::INFO_ID_SYNCHRONIZED);

    // 5: Import entity in en: en and fr should indicate synchronized.
    $this->pullChannel('node_es_test_en');
    $this->jsonapiHelper->clearImportedEntities();
    $this->expectedState(StateInformationInterface::INFO_ID_SYNCHRONIZED, StateInformationInterface::INFO_ID_SYNCHRONIZED);

    // 6: Edit the fr translation (also this emulates a change on the client
    // website): en should indicate synchronized and fr should indicate changed.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->loadEntity('node', 'es_test');
    $node_translation = $node->getTranslation('fr');
    $node_translation->setChangedTime($this->faker->unixTime());
    $node_translation->save();
    $this->expectedState(StateInformationInterface::INFO_ID_SYNCHRONIZED, StateInformationInterface::INFO_ID_CHANGED);

    // 7: Import entity in fr: en and fr should indicate synchronized.
    $this->pullChannel('node_es_test_fr');
    $this->jsonapiHelper->clearImportedEntities();
    $this->expectedState(StateInformationInterface::INFO_ID_SYNCHRONIZED, StateInformationInterface::INFO_ID_SYNCHRONIZED);
  }

  /**
   * {@inheritdoc}
   */
  protected function createChannel(UserInterface $user) {
    parent::createChannel($user);

    // Add a channel for the node in french.
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => 'node_es_test_fr',
      'label' => $this->randomString(),
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'fr',
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
   * @param string $en_expected_state
   *   The expected state for the en translation.
   * @param string $fr_expected_state
   *   The expected state for the fr translation.
   */
  protected function expectedState($en_expected_state, $fr_expected_state) {
    $json_data = $this->getEntityJsonData('node_es_test_en', 'es_test');
    $status = $this->stateInformation->getStatusInfo($json_data);
    $this->assertEqual($status['info_id'], $en_expected_state);

    $json_data = $this->getEntityJsonData('node_es_test_fr', 'es_test');
    $status = $this->stateInformation->getStatusInfo($json_data);
    $this->assertEqual($status['info_id'], $fr_expected_state);
  }

}
