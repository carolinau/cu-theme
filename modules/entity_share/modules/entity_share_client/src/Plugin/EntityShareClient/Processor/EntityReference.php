<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\Event\RelationshipFieldValueEvent;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handle entity reference.
 *
 * @ImportProcessor(
 *   id = "entity_reference",
 *   label = @Translation("Entity reference"),
 *   description = @Translation("Handle entity reference fields."),
 *   stages = {
 *     "process_entity" = 10,
 *   },
 *   locked = true,
 * )
 */
class EntityReference extends ImportProcessorPluginBase implements PluginFormInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $entityDefinitions;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The current recursion depth.
   *
   * @var int
   */
  protected $currentRecursionDepth = 0;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $entity_type_manager = $container->get('entity_type.manager');
    $instance->entityTypeManager = $entity_type_manager;
    $instance->entityDefinitions = $entity_type_manager->getDefinitions();
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->remoteManager = $container->get('entity_share_client.remote_manager');
    $instance->logger = $container->get('logger.channel.entity_share_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'max_recursion_depth' => -1,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['max_recursion_depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum recursion depth'),
      '#description' => $this->t('The maximum recursion depth. -1 for unlimited. When reaching max recursion depth, referenced entities are set if the entity already exists on the website.'),
      '#default_value' => $this->configuration['max_recursion_depth'],
      '#min' => -1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function processEntity(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity, array $entity_json_data) {
    if (isset($entity_json_data['relationships'])) {
      $field_mappings = $runtime_import_context->getFieldMappings();
      // Loop on reference fields.
      foreach ($entity_json_data['relationships'] as $field_public_name => $field_data) {
        $field_internal_name = array_search($field_public_name, $field_mappings[$processed_entity->getEntityTypeId()][$processed_entity->bundle()]);
        $field = $processed_entity->get($field_internal_name);

        if (!$this->relationshipHandleable($field)) {
          continue;
        }

        $main_property = $field->getItemDefinition()->getMainPropertyName();
        $field_values = [];

        // Check that the field has data.
        if ($field_data['data'] != NULL) {
          $prepared_field_data = EntityShareUtility::prepareData($field_data['data']);
          $referenced_entities_ids = [];

          // Max recursion depth reached. Reference only existing entities.
          if ($this->currentRecursionDepth == $this->configuration['max_recursion_depth']) {
            $referenced_entities_ids = $this->getExistingEntities($prepared_field_data);
          }
          // Import referenced entities.
          elseif (isset($field_data['links']['related']['href'])) {
            $referenced_entities_ids = $this->importUrl($runtime_import_context, $field_data['links']['related']['href']);
            // It is possible that some entities have been skipped from import,
            // but do exist, so ensure that those are available to the
            // mapping code below.
            $referenced_entities_ids = $this->getExistingEntities($prepared_field_data) + $referenced_entities_ids;
          }

          // Add field value.
          // As the loop is on the JSON:API data, the sort is preserved.
          foreach ($prepared_field_data as $field_value_data) {
            $referenced_entity_uuid = $field_value_data['id'];

            // Check that the referenced entity exists or had been imported.
            if (!isset($referenced_entities_ids[$referenced_entity_uuid])) {
              continue;
            }

            $field_value = [
              $main_property => $referenced_entities_ids[$referenced_entity_uuid],
            ];
            // Add field metadatas.
            if (isset($field_value_data['meta'])) {
              $field_value += $field_value_data['meta'];
            }

            // Allow to alter the field value with an event.
            $event = new RelationshipFieldValueEvent($field, $field_value);
            $this->eventDispatcher->dispatch(RelationshipFieldValueEvent::EVENT_NAME, $event);
            $field_values[] = $event->getFieldValue();
          }
        }
        $processed_entity->set($field_public_name, $field_values);
      }

      // TODO: Test if this is still needed.
      // Save the entity once all the references have been updated.
      $processed_entity->save();
    }
  }

  /**
   * Check if a relationship is handleable.
   *
   * Filter on fields not targeting config entities or users.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list.
   *
   * @return bool
   *   TRUE if the relationship is handleable.
   */
  protected function relationshipHandleable(FieldItemListInterface $field) {
    $relationship_handleable = FALSE;

    if ($field instanceof EntityReferenceFieldItemListInterface) {
      $settings = $field->getItemDefinition()->getSettings();

      // Entity reference and Entity reference revisions.
      if (isset($settings['target_type'])) {
        $relationship_handleable = !$this->isUserOrConfigEntity($settings['target_type']);
      }
      // Dynamic entity reference.
      elseif (isset($settings['entity_type_ids'])) {
        foreach ($settings['entity_type_ids'] as $entity_type_id) {
          $relationship_handleable = !$this->isUserOrConfigEntity($entity_type_id);
          if (!$relationship_handleable) {
            break;
          }
        }
      }
    }

    return $relationship_handleable;
  }

  /**
   * Helper function to check if an entity type id is a user or a config entity.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return bool
   *   TRUE if the entity type is user or a config entity. FALSE otherwise.
   */
  protected function isUserOrConfigEntity($entity_type_id) {
    if ($entity_type_id == 'user') {
      return TRUE;
    }
    elseif ($this->entityDefinitions[$entity_type_id]->getGroup() == 'configuration') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Helper function to get existing reference entities.
   *
   * @param array $data
   *   The JSON:API data for an entity reference field.
   *
   * @return array
   *   An array of entity IDs keyed by UUID.
   */
  protected function getExistingEntities(array $data) {
    $referenced_entities_ids = [];
    $entity_uuids = [];

    // Extract list of UUIDs.
    foreach ($data as $field_value_data) {
      if ($field_value_data['id'] !== 'missing') {
        $parsed_type = explode('--', $field_value_data['type']);
        $entity_type_id = $parsed_type[0];
        $entity_uuids[] = $field_value_data['id'];
      }
    }

    if (!empty($entity_uuids)) {
      try {
        // Load the entities to be able to return an array of IDs keyed by
        // UUIDs. Sorting the array will be done later.
        $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
        $existing_entity_ids = $entity_storage->getQuery()
          ->condition('uuid', $entity_uuids, 'IN')
          ->execute();

        $existing_entities = $entity_storage->loadMultiple($existing_entity_ids);
        foreach ($existing_entities as $existing_entity) {
          $referenced_entities_ids[$existing_entity->uuid()] = $existing_entity->id();
        }
      }
      catch (\Exception $e) {
        $log_variables = [];
        $log_variables['@msg'] = $e->getMessage();
        $this->logger->error('Caught exception trying to load existing entities. Error message was @msg', $log_variables);
      }
    }

    return $referenced_entities_ids;
  }

  /**
   * Helper function.
   *
   * @param \Drupal\entity_share_client\RuntimeImportContext $runtime_import_context
   *   The runtime import context.
   * @param string $url
   *   The URL to import.
   *
   * @return array
   *   The list of entity IDs imported keyed by UUIDs.
   */
  protected function importUrl(RuntimeImportContext $runtime_import_context, $url) {
    $referenced_entities_ids = [];
    $referenced_entities_response = $this->remoteManager->jsonApiRequest($runtime_import_context->getRemote(), 'GET', $url);
    $referenced_entities_json = Json::decode((string) $referenced_entities_response->getBody());

    // $referenced_entities_json['data'] can be null in the case of
    // missing/deleted referenced entities.
    if (!isset($referenced_entities_json['errors']) && !is_null($referenced_entities_json['data'])) {
      $this->currentRecursionDepth++;
      $referenced_entities_ids = $runtime_import_context->getImportService()->importEntityListData($referenced_entities_json['data']);
      $this->currentRecursionDepth--;
    }

    return $referenced_entities_ids;
  }

}
