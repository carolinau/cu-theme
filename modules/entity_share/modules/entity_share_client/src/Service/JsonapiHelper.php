<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\Entity\RemoteInterface;
use Drupal\entity_share_client\Event\RelationshipFieldValueEvent;
use Drupal\file\FileInterface;
use Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Drupal\entity_share_client\Event\EntityListDataAlterEvent;
use Drupal\entity_share_client\Event\EntityInsertEvent;
use Drupal\entity_share_client\Event\EntityAlterEvent;

/**
 * Class JsonapiHelper.
 *
 * @package Drupal\entity_share_client\Service
 */
class JsonapiHelper implements JsonapiHelperInterface {

  use StringTranslationTrait;

  /**
   * The format for the remote changed time.
   *
   * Long format.
   */
  const CHANGED_FORMAT = 'l, F j, Y - H:i';

  /**
   * The JsonApiDocumentTopLevelNormalizer normalizer.
   *
   * @var \Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer
   */
  protected $jsonapiDocumentTopLevelNormalizer;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The bundle infos from the website.
   *
   * @var array
   */
  protected $bundleInfos;

  /**
   * The entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $entityDefinitions;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The request service.
   *
   * @var \Drupal\entity_share_client\Service\RequestServiceInterface
   */
  protected $requestService;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * A prepared HTTP client for file transfer.
   *
   * @var \GuzzleHttp\Client
   */
  protected $fileHttpClient;

  /**
   * A prepared HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The remote website on which to prepare the clients.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface
   */
  protected $remote;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The state information service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $stateInformation;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The list of the currently imported entities.
   *
   * @var array
   */
  protected $importedEntities;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * JsonapiHelper constructor.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   A serializer.
   * @param \Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer $jsonapi_document_top_level_normalizer
   *   The JsonApiDocumentTopLevelNormalizer normalizer.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\entity_share_client\Service\RemoteManagerInterface $remote_manager
   *   The remote manager service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   * @param \Drupal\entity_share_client\Service\RequestServiceInterface $request_service
   *   The request service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\entity_share_client\Service\StateInformationInterface $state_information
   *   The state information service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   */
  public function __construct(
    SerializerInterface $serializer,
    JsonApiDocumentTopLevelNormalizer $jsonapi_document_top_level_normalizer,
    ResourceTypeRepositoryInterface $resource_type_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    StreamWrapperManagerInterface $stream_wrapper_manager,
    LanguageManagerInterface $language_manager,
    RemoteManagerInterface $remote_manager,
    EventDispatcherInterface $event_dispatcher,
    RequestServiceInterface $request_service,
    MessengerInterface $messenger,
    LoggerInterface $logger,
    StateInformationInterface $state_information,
    ModuleHandlerInterface $module_handler,
    FileSystemInterface $fileSystem
  ) {
    $this->jsonapiDocumentTopLevelNormalizer = $jsonapi_document_top_level_normalizer;
    $this->jsonapiDocumentTopLevelNormalizer->setSerializer($serializer);
    $this->resourceTypeRepository = $resource_type_repository;
    $this->bundleInfos = $entity_type_bundle_info->getAllBundleInfo();
    $this->entityDefinitions = $entity_type_manager->getDefinitions();
    $this->entityTypeManager = $entity_type_manager;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->languageManager = $language_manager;
    $this->remoteManager = $remote_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->requestService = $request_service;
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->stateInformation = $state_information;
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $fileSystem;
    $this->importedEntities = [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntitiesOptions(array $json_data, RemoteInterface $remote, $channel_id) {
    $options = [];
    foreach (EntityShareUtility::prepareData($json_data) as $data) {
      $this->addOptionFromJson($options, $data, $remote, $channel_id);
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function extractEntity(array $data) {
    // Format JSON as in
    // JsonApiDocumentTopLevelNormalizerTest::testDenormalize().
    $prepared_json = [
      'data' => [
        'type' => $data['type'],
        'attributes' => $data['attributes'],
      ],
    ];
    $parsed_type = explode('--', $data['type']);

    return $this->jsonapiDocumentTopLevelNormalizer->denormalize($prepared_json, NULL, 'api_json', [
      'resource_type' => $this->resourceTypeRepository->get(
        $parsed_type[0],
        $parsed_type[1]
      ),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function updateRelationships(ContentEntityInterface $entity, array $data) {
    if (isset($data['relationships'])) {
      $resource_type = $this->resourceTypeRepository->get(
        $entity->getEntityTypeId(),
        $entity->bundle()
      );
      // Reference fields.
      foreach ($data['relationships'] as $field_name => $field_data) {
        $field_name = $resource_type->getInternalName($field_name);
        $field = $entity->get($field_name);
        if ($this->relationshipHandleable($field)) {
          $field_values = [];

          // Check that the field has data.
          if ($field_data['data'] != NULL && isset($field_data['links']['related']['href'])) {
            $referenced_entities_response = $this->requestService->request($this->getHttpClient(), 'GET', $field_data['links']['related']['href']);
            $referenced_entities_json = Json::decode((string) $referenced_entities_response->getBody());

            // $referenced_entities_json['data'] can be null in the case of
            // missing/deleted referenced entities.
            if (!isset($referenced_entities_json['errors']) && !is_null($referenced_entities_json['data'])) {
              $referenced_entities_ids = $this->importEntityListData($referenced_entities_json['data']);

              $main_property = $field->getItemDefinition()->getMainPropertyName();

              // Remove the missing entities from the array to avoid key
              // mismatch.
              $prepared_data = [];
              foreach (EntityShareUtility::prepareData($field_data['data']) as $field_value_data) {
                if ($field_value_data['id'] !== 'missing') {
                  $prepared_data[] = $field_value_data;
                }
              }

              // Add field metadatas.
              foreach ($prepared_data as $key => $field_value_data) {
                // When dealing with taxonomy term entities which has a
                // hierarchy, there is a virtual entity for the root. So
                // $referenced_entities_ids[$key] may not exist.
                // See https://www.drupal.org/node/2976856.
                if (isset($referenced_entities_ids[$key])) {
                  $field_value = [
                    $main_property => $referenced_entities_ids[$key],
                  ];

                  if (isset($field_value_data['meta'])) {
                    $field_value += $field_value_data['meta'];
                  }

                  // Allow to alter the field value with an event.
                  $event = new RelationshipFieldValueEvent($field, $field_value);
                  $this->eventDispatcher->dispatch(RelationshipFieldValueEvent::EVENT_NAME, $event);
                  $field_values[] = $event->getFieldValue();
                }
              }
            }
          }
          $entity->set($field_name, $field_values);
        }
      }

      // Save the entity once all the references have been updated.
      $entity->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handlePhysicalFiles(ContentEntityInterface $entity, array &$data) {
    if ($entity instanceof FileInterface) {
      $resource_type = $this->resourceTypeRepository->get(
        $entity->getEntityTypeId(),
        $entity->bundle()
      );
      $uri_public_name = $resource_type->getPublicName('uri');

      $remote_uri = $data['attributes'][$uri_public_name]['value'];
      $remote_url = $data['attributes'][$uri_public_name]['url'];
      $stream_wrapper = $this->streamWrapperManager->getViaUri($remote_uri);
      $directory_uri = $stream_wrapper->dirname($remote_uri);
      $log_variables = [
        '%url' => $remote_url,
        '%directory' => $directory_uri,
        '%id' => $entity->id(),
        '%uri' => $remote_uri,
      ];

      // Create the destination folder.
      if ($this->fileSystem->prepareDirectory($directory_uri, FileSystemInterface::CREATE_DIRECTORY)) {
        try {
          $response = $this->requestService->request($this->getFileHttpClient(), 'GET', $remote_url);
          $file_content = (string) $response->getBody();
          $result = @file_put_contents($remote_uri, $file_content);
          if (!$result) {
            throw new \Exception('Error writing file to ' . $remote_uri);
          }
        }
        catch (ClientException $e) {
          $this->messenger->addWarning($this->t('Error importing file id %id. Missing file: %url', $log_variables));
          $this->logger->warning('Error importing file id %id. Missing file: %url', $log_variables);
        }
        catch (\Throwable $e) {
          $log_variables['@msg'] = $e->getMessage();
          $this->messenger->addError($this->t('Caught exception trying to import the file %url to %uri', $log_variables));
          $this->logger->error('Caught exception trying to import the file %url to %uri. Error message was @msg', $log_variables);
        }
      }
      else {
        $this->messenger->addError($this->t('Impossible to write in the directory %directory', $log_variables));
        $this->logger->error('Impossible to write in the directory %directory', $log_variables);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setRemote(RemoteInterface $remote) {
    $this->remote = $remote;
  }

  /**
   * {@inheritdoc}
   */
  public function importEntityListData(array $entity_list_data) {
    // Allow other modules to alter the entity data with an EventSubscriber.
    $event = new EntityListDataAlterEvent($entity_list_data, $this->remote);
    $this->eventDispatcher->dispatch(EntityListDataAlterEvent::EVENT_NAME, $event);
    $entity_list_data = $event->getEntityListData();

    $imported_entity_ids = [];
    foreach (EntityShareUtility::prepareData($entity_list_data) as $entity_data) {
      $parsed_type = explode('--', $entity_data['type']);
      $entity_type_id = $parsed_type[0];
      $entity_bundle = $parsed_type[1];
      $resource_type = $this->resourceTypeRepository->get(
        $entity_type_id,
        $entity_bundle
      );
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity_keys = $entity_storage->getEntityType()->getKeys();

      $this->prepareEntityData($entity_data, $entity_keys);

      $data_langcode = !empty($entity_keys['langcode']) ? $entity_data['attributes'][$resource_type->getPublicName($entity_keys['langcode'])] : LanguageInterface::LANGCODE_NOT_SPECIFIED;

      // Prepare entity label.
      if (isset($entity_keys['label'])) {
        $entity_label = $entity_data['attributes'][$resource_type->getPublicName($entity_keys['label'])];
      }
      else {
        // Use the entity type if there is no label.
        $entity_label = $entity_type_id;
      }

      if ($data_langcode && !$this->dataLanguageExists($data_langcode, $entity_label)) {
        continue;
      }

      // Check if an entity already exists.
      // JSON:API no longer includes uuid in attributes so we're using id
      // instead. See https://www.drupal.org/node/2984247.
      $existing_entities = $entity_storage
        ->loadByProperties(['uuid' => $entity_data['id']]);

      // Here is the supposition that we are importing a list of content
      // entities. Currently this is ensured by the fact that it is not possible
      // to make a channel on config entities and on users. And that in the
      // relationshipHandleable() method we prevent handling config entities and
      // users relationships.
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $this->extractEntity($entity_data);

      // New entity.
      if (empty($existing_entities)) {
        // Allow other modules to alter the entity with an EventSubscriber.
        $event = new EntityInsertEvent($entity, $this->remote);
        $this->eventDispatcher->dispatch(EntityInsertEvent::EVENT_NAME, $event);

        $entity->save();
        $imported_entity_ids[] = $entity->id();
        // Prevent the entity of being reimported.
        $this->importedEntities[$entity->language()->getId()][$entity->uuid()] = $entity->uuid();
        $this->updateRelationships($entity, $entity_data);
        $this->handlePhysicalFiles($entity, $entity_data);
        $this->setChangedTime($entity, $resource_type, $entity_data);
        $entity->save();
      }
      // Update the existing entity.
      else {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $existing_entity */
        $existing_entity = array_shift($existing_entities);
        $imported_entity_ids[] = $existing_entity->id();

        if (!isset($this->importedEntities[$data_langcode][$existing_entity->uuid()])) {
          // Prevent the entity translation of being reimported.
          $this->importedEntities[$data_langcode][$existing_entity->uuid()] = $existing_entity->uuid();
          $has_translation = $existing_entity->hasTranslation($data_langcode);
          // Update the existing translation.
          if ($has_translation) {
            $resource_type = $this->resourceTypeRepository->get(
              $entity->getEntityTypeId(),
              $entity->bundle()
            );
            $existing_translation = $existing_entity->getTranslation($data_langcode);
            foreach ($entity_data['attributes'] as $field_name => $value) {
              $field_name = $resource_type->getInternalName($field_name);
              $existing_translation->set(
                $field_name,
                $entity->get($field_name)->getValue()
              );
            }
            // Allow other modules to alter the entity with an EventSubscriber.
            $event = new EntityAlterEvent($existing_translation, $this->remote);
            $this->eventDispatcher->dispatch(EntityAlterEvent::EVENT_NAME, $event);
            $existing_translation->save();
          }
          // Create the new translation.
          else {
            $translation = $entity->toArray();
            $existing_entity->addTranslation($data_langcode, $translation);
            // Allow other modules to alter the entity translation with an
            // EventSubscriber.
            $event = new EntityAlterEvent($existing_entity->getTranslation($data_langcode), $this->remote);
            $this->eventDispatcher->dispatch(EntityAlterEvent::EVENT_NAME, $event);
            $existing_entity->save();
            $existing_translation = $existing_entity->getTranslation($data_langcode);
          }
          $this->updateRelationships($existing_translation, $entity_data);
          $this->handlePhysicalFiles($existing_translation, $entity_data);
          $this->setChangedTime($existing_translation, $resource_type, $entity_data);
          $existing_translation->save();
        }
      }
    }
    return $imported_entity_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function clearImportedEntities($langcode = '', $entity_uuid = '') {
    if (empty($langcode) && empty($entity_uuid)) {
      $this->importedEntities = [];
    }
    elseif (!empty($langcode) && empty(!$entity_uuid) && isset($this->importedEntities[$langcode][$entity_uuid])) {
      unset($this->importedEntities[$langcode][$entity_uuid]);
    }
    elseif (!empty($langcode) && isset($this->importedEntities[$langcode])) {
      $this->importedEntities[$langcode] = [];
    }
    elseif (!empty($entity_uuid)) {
      foreach ($this->importedEntities as $imported_entities_langcode => $imported_entities_uuids) {
        if (isset($this->importedEntities[$imported_entities_langcode][$entity_uuid])) {
          unset($this->importedEntities[$imported_entities_langcode][$entity_uuid]);
        }
      }
    }
  }

  /**
   * Helper function to add an option.
   *
   * @param array $options
   *   The array of options for the tableselect form type element.
   * @param array $data
   *   An array of data.
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The selected remote.
   * @param string $channel_id
   *   The selected channel id.
   * @param int $level
   *   The level of indentation.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \InvalidArgumentException
   */
  protected function addOptionFromJson(array &$options, array $data, RemoteInterface $remote, $channel_id, $level = 0) {
    $parsed_type = explode('--', $data['type']);
    $entity_type_id = $parsed_type[0];
    $bundle_id = $parsed_type[1];

    $entity_type = $this->entityTypeManager->getStorage($entity_type_id)->getEntityType();
    $entity_keys = $entity_type->getKeys();

    $resource_type = $this->resourceTypeRepository->get(
      $entity_type_id,
      $bundle_id
    );

    $status_info = $this->stateInformation->getStatusInfo($data);

    // Prepare remote changed info.
    $remote_changed_info = '';
    if ($resource_type->hasField('changed')) {
      $changed_public_name = $resource_type->getPublicName('changed');
      if (!empty($data['attributes'][$changed_public_name])) {
        if (is_numeric($data['attributes'][$changed_public_name])) {
          $remote_changed_date = DrupalDateTime::createFromTimestamp($data['attributes'][$changed_public_name]);
          $remote_changed_info = $remote_changed_date->format(self::CHANGED_FORMAT, [
            'timezone' => date_default_timezone_get(),
          ]);
        }
        elseif ($remote_changed_date = DrupalDateTime::createFromFormat(\DateTime::RFC3339, $data['attributes'][$changed_public_name])) {
          $remote_changed_info = $remote_changed_date->format(self::CHANGED_FORMAT, [
            'timezone' => date_default_timezone_get(),
          ]);
        }
      }
    }

    $options[$data['id']] = [
      'label' => $this->getOptionLabel($data, $status_info, $entity_keys, $remote->get('url'), $level),
      'type' => $entity_type->getLabel(),
      'bundle' => $this->bundleInfos[$entity_type_id][$bundle_id]['label'],
      'language' => $this->getEntityLanguageLabel($data, $entity_keys),
      'changed' => $remote_changed_info,
      'status' => [
        'data' => $status_info['label'],
        'class' => $status_info['class'],
      ],
    ];

    $id_public_name = $resource_type->getPublicName($entity_keys['id']);
    if ($this->moduleHandler->moduleExists('diff') &&
      in_array($status_info['info_id'], [
        StateInformationInterface::INFO_ID_CHANGED,
        StateInformationInterface::INFO_ID_NEW_TRANSLATION,
      ]) &&
      !is_null($status_info['local_revision_id']) &&
      isset($data['attributes'][$id_public_name])
    ) {
      $options[$data['id']]['status']['data'] = new FormattableMarkup('@label: @diff_link', [
        '@label' => $options[$data['id']]['status']['data'],
        '@diff_link' => Link::createFromRoute($this->t('Diff'), 'entity_share_client.diff', [
          'left_revision' => $status_info['local_revision_id'],
          'remote' => $remote->id(),
          'channel_id' => $channel_id,
          'uuid' => $data['id'],
        ], [
          'attributes' => [
            'class' => [
              'use-ajax',
            ],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode(['width' => '90%']),
          ],
        ])->toString(),
      ]);
    }
  }

  /**
   * Helper function to calculate the label to display in the table.
   *
   * @param array $data
   *   An array of data.
   * @param array $status_info
   *   An array of status info as returned by
   *   StateInformationInterface::getStatusInfo().
   * @param array $entity_keys
   *   The entity keys.
   * @param string $remote_url
   *   The remote url.
   * @param int $level
   *   The level of indentation.
   *
   * @return \Drupal\Component\Render\FormattableMarkup|string
   *   The prepared label.
   */
  protected function getOptionLabel(array $data, array $status_info, array $entity_keys, $remote_url, $level) {
    $indentation = '';
    for ($i = 1; $i <= $level; $i++) {
      $indentation .= '<div class="indentation">&nbsp;</div>';
    }

    $parsed_type = explode('--', $data['type']);
    $entity_type_id = $parsed_type[0];
    $bundle_id = $parsed_type[1];

    $resource_type = $this->resourceTypeRepository->get(
      $entity_type_id,
      $bundle_id
    );
    $label_public_name = FALSE;
    if (isset($entity_keys['label']) && $resource_type->hasField($entity_keys['label'])) {
      $label_public_name = $resource_type->getPublicName($entity_keys['label']);
    }

    // Some entity type may not have a label key and the label is calculated
    // using the label() method on the entity but at this step the entity is not
    // denormalized and also as we are not on the server website, we would not
    // have the data required to calculate the entity's label.
    if (isset($data['attributes'][$label_public_name])) {
      $label = $data['attributes'][$label_public_name];
    }
    elseif (isset($entity_keys['id']) && $resource_type->hasField($entity_keys['id'])) {
      $label = $data['attributes'][$resource_type->getPublicName($entity_keys['id'])];
    }
    else {
      $label = $data['id'];
    }

    // Get link to remote entity. Need to manually create the link to avoid
    // getting alias from local website.
    if (isset($entity_keys['id']) && $resource_type->hasField($entity_keys['id'])) {
      $remote_entity_id = $data['attributes'][$resource_type->getPublicName($entity_keys['id'])];
      $entity_definition = $this->entityDefinitions[$entity_type_id];

      if ($entity_definition->hasLinkTemplate('canonical')) {
        $canonical_path = $entity_definition->getLinkTemplate('canonical');
        $remote_entity_path = str_replace('{' . $entity_type_id . '}', $remote_entity_id, $canonical_path);
        $remote_entity_url = Url::fromUri($remote_url . $remote_entity_path);

        $label = Link::fromTextAndUrl($label, $remote_entity_url)->toString();
      }
    }

    // Prepare link to local entity if it exists.
    $local_link = '';
    if (!is_null($status_info['local_entity_link'])) {
      $local_link = new Link($this->t('(View local)'), $status_info['local_entity_link']);
      $local_link = $local_link->toString();
    }

    $label = new FormattableMarkup($indentation . '@label ' . $local_link, [
      '@label' => $label,
    ]);

    return $label;
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
   * Helper function to get the language from an extracted entity.
   *
   * We can't use $entity->language() because if the entity is in a language not
   * enabled, it is the site default language that is returned.
   *
   * @param array $data
   *   The data from the JSON:API payload.
   * @param array $entity_keys
   *   The entity keys from the entity definition.
   *
   * @return string
   *   The language of the entity.
   */
  protected function getEntityLanguageLabel(array $data, array $entity_keys) {
    if (!isset($entity_keys['langcode']) || empty($entity_keys['langcode'])) {
      return $this->t('Untranslatable entity');
    }

    $parsed_type = explode('--', $data['type']);
    $resource_type = $this->resourceTypeRepository->get(
      $parsed_type[0],
      $parsed_type[1]
    );
    $langcode = $data['attributes'][$resource_type->getPublicName($entity_keys['langcode'])];
    $language = $this->languageManager->getLanguage($langcode);
    // Check if the entity is in an enabled language.
    if (is_null($language)) {
      $language_list = LanguageManager::getStandardLanguageList();
      if (isset($language_list[$langcode])) {
        $entity_language = $language_list[$langcode][0] . ' ' . $this->t('(not enabled)', [], ['context' => 'language']);
      }
      else {
        $entity_language = $this->t('Entity in an unsupported language.');
      }
    }
    else {
      $entity_language = $language->getName();
    }

    return $entity_language;
  }

  /**
   * Helper function to get the File Http Client.
   *
   * @return \GuzzleHttp\Client
   *   A HTTP client to retrieve files.
   */
  protected function getFileHttpClient() {
    if (!$this->fileHttpClient) {
      $this->fileHttpClient = $this->remoteManager->prepareClient($this->remote);
    }

    return $this->fileHttpClient;
  }

  /**
   * Helper function to get the Http Client.
   *
   * @return \GuzzleHttp\Client
   *   A HTTP client to request JSON:API endpoints.
   */
  protected function getHttpClient() {
    if (!$this->httpClient) {
      $this->httpClient = $this->remoteManager->prepareJsonApiClient($this->remote);
    }

    return $this->httpClient;
  }

  /**
   * Prepare the data array before extracting the entity.
   *
   * Used to remove some data.
   *
   * @param array $data
   *   An array of data.
   * @param array $entity_keys
   *   An array of entity keys.
   */
  protected function prepareEntityData(array &$data, array $entity_keys) {
    $parsed_type = explode('--', $data['type']);

    $resource_type = $this->resourceTypeRepository->get(
      $parsed_type[0],
      $parsed_type[1]
    );

    // Removes some ids.
    unset($data['attributes'][$resource_type->getPublicName($entity_keys['id'])]);
    if (isset($entity_keys['revision']) && !empty($entity_keys['revision'])) {
      unset($data['attributes'][$resource_type->getPublicName($entity_keys['revision'])]);
    }

    // UUID is no longer included as attribute.
    $data['attributes'][$resource_type->getPublicName($entity_keys['uuid'])] = $data['id'];

    // Remove the default_langcode boolean to be able to import content not
    // necessarily in the default language.
    unset($data['attributes'][$resource_type->getPublicName($entity_keys['default_langcode'])]);
  }

  /**
   * Check if we try to import an entity in a disabled language.
   *
   * @param string $langcode
   *   The langcode of the language to check.
   * @param string $entity_label
   *   The entity label.
   *
   * @return bool
   *   FALSE if the data is not in an enabled language.
   */
  protected function dataLanguageExists($langcode, $entity_label) {
    if (is_null($this->languageManager->getLanguage($langcode))) {
      $this->messenger->addError($this->t('Trying to import an entity (%entity_label) in a disabled language.', [
        '%entity_label' => $entity_label,
      ]));
      return FALSE;
    }

    return TRUE;
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
   * Change the entity "changed" time.
   *
   * Because it could have been altered with relationship saved by example.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to update.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type related to the entity.
   * @param array $entity_data
   *   The JSON data.
   */
  protected function setChangedTime(ContentEntityInterface $entity, ResourceType $resource_type, array $entity_data) {
    $changed_public_name = FALSE;
    if ($resource_type->hasField('changed')) {
      $changed_public_name = $resource_type->getPublicName('changed');
    }

    if (
      $changed_public_name &&
      !empty($entity_data['attributes'][$changed_public_name]) &&
      method_exists($entity, 'setChangedTime')
    ) {
      // If the website is using backward compatible timestamps output.
      // @see https://www.drupal.org/node/2859657.
      if (is_numeric($entity_data['attributes'][$changed_public_name])) {
        // The value is casted in integer for
        // https://www.drupal.org/node/2837696.
        $entity->setChangedTime((int) $entity_data['attributes'][$changed_public_name]);
      }
      elseif ($changed_datetime = \DateTime::createFromFormat(\DateTime::RFC3339, $entity_data['attributes'][$changed_public_name])) {
        $entity->setChangedTime($changed_datetime->getTimestamp());
      }
    }
  }

}
