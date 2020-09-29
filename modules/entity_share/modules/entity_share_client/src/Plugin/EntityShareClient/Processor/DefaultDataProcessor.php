<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General default data processor.
 *
 * @ImportProcessor(
 *   id = "default_data_processor",
 *   label = @Translation("Default data processor"),
 *   description = @Translation("General JSON data preparation to have Entity Share import working."),
 *   stages = {
 *     "is_entity_importable" = -10,
 *     "prepare_importable_entity_data" = -100,
 *     "post_entity_save" = 0,
 *   },
 *   locked = true,
 * )
 */
class DefaultDataProcessor extends ImportProcessorPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The Entity import state information service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $stateInformation;

  /**
   * The Drupal datetime service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->logger = $container->get('logger.channel.entity_share_client');
    $instance->languageManager = $container->get('language_manager');
    $instance->stateInformation = $container->get('entity_share_client.state_information');
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isEntityImportable(RuntimeImportContext $runtime_import_context, array $entity_json_data) {
    $field_mappings = $runtime_import_context->getFieldMappings();
    $parsed_type = explode('--', $entity_json_data['type']);
    $entity_type_id = $parsed_type[0];
    $entity_bundle = $parsed_type[1];
    // TODO: Refactor in attributes to avoid getting entity keys each time.
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_keys = $entity_storage->getEntityType()->getKeys();

    $langcode_public_name = FALSE;
    if (!empty($entity_keys['langcode']) && isset($field_mappings[$entity_type_id][$entity_bundle][$entity_keys['langcode']])) {
      $langcode_public_name = $field_mappings[$entity_type_id][$entity_bundle][$entity_keys['langcode']];
    }

    $data_langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    if ($langcode_public_name && !empty($entity_json_data['attributes'][$langcode_public_name])) {
      $data_langcode = $entity_json_data['attributes'][$langcode_public_name];
    }

    // Check if we try to import an entity with langcode in a disabled language.
    if (is_null($this->languageManager->getLanguage($data_langcode))) {
      // Use the entity type if there is no label.
      $entity_label = $entity_type_id;
      // Prepare entity label.
      if (isset($entity_keys['label']) && isset($field_mappings[$entity_type_id][$entity_bundle][$entity_keys['label']])) {
        $label_public_name = $field_mappings[$entity_type_id][$entity_bundle][$entity_keys['label']];
        if (!empty($entity_json_data['attributes'][$label_public_name])) {
          $entity_label = $entity_json_data['attributes'][$label_public_name];
        }
      }

      $log_variables = [
        '%entity_label' => $entity_label,
      ];

      $this->logger->error('Trying to import an entity (%entity_label) in a disabled language.', $log_variables);
      $this->messenger()->addError($this->t('Trying to import an entity (%entity_label) in a disabled language.', $log_variables));
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareImportableEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
    $field_mappings = $runtime_import_context->getFieldMappings();
    $parsed_type = explode('--', $entity_json_data['type']);
    $entity_type_id = $parsed_type[0];
    $entity_bundle = $parsed_type[1];
    // TODO: Refactor in attributes to avoid getting entity keys each time.
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_keys = $entity_storage->getEntityType()->getKeys();

    $entity_keys_to_remove = [
      'id',
      'revision',
      // Remove the default_langcode boolean to be able to import content not
      // necessarily in the default language.
      'default_langcode',
    ];

    foreach ($entity_keys_to_remove as $entity_key_to_remove) {
      if (!isset($entity_keys[$entity_key_to_remove])) {
        continue;
      }
      // If there is nothing in the field mapping, the field should have been
      // disabled on the server website using JSON:API extras.
      if (!isset($field_mappings[$entity_type_id][$entity_bundle][$entity_keys[$entity_key_to_remove]])) {
        continue;
      }

      $public_name_to_remove = $field_mappings[$entity_type_id][$entity_bundle][$entity_keys[$entity_key_to_remove]];
      if (isset($entity_json_data['attributes'][$public_name_to_remove])) {
        unset($entity_json_data['attributes'][$public_name_to_remove]);
      }
    }

    // UUID is no longer included as attribute.
    $uuid_public_name = 'uuid';
    if (!empty($entity_keys['uuid']) && isset($field_mappings[$entity_type_id][$entity_bundle][$entity_keys['uuid']])) {
      $uuid_public_name = $field_mappings[$entity_type_id][$entity_bundle][$entity_keys['uuid']];
    }
    $entity_json_data['attributes'][$uuid_public_name] = $entity_json_data['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function postEntitySave(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity) {
    // Create or update the dedicated "Entity import status" entity.
    // At this point the entity has been successfully imported.
    if (!$import_status_entity = $this->stateInformation->getImportStatusOfEntity($processed_entity)) {
      // If a dedicated "Entity import status" entity doesn't exist (which
      // means that either this is a new imported entity, or it is a "legacy"
      // content imported before the introduction of "Entity import status"
      // entities), create it.
      $parameters = [
        'remote_website' => $runtime_import_context->getRemote()->id(),
        'channel_id' => $runtime_import_context->getChannelId(),
      ];
      $this->stateInformation->createImportStatusOfEntity($processed_entity, $parameters);
    }
    else {
      // "Entity import status" exists, just update the last import timestamp.
      $import_status_entity->setLastImport($this->time->getRequestTime())->save();
    }
  }

}
