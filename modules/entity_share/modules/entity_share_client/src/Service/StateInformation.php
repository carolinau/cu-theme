<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Class StateInformation.
 *
 * @package Drupal\entity_share_client\Service
 */
class StateInformation implements StateInformationInterface {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * StateInformation constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ResourceTypeRepositoryInterface $resource_type_repository
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusInfo(array $data) {
    $status_info = [
      'label' => $this->t('Undefined'),
      'class' => 'entity-share-undefined',
      'info_id' => StateInformationInterface::INFO_ID_UNDEFINED,
      'local_entity_link' => NULL,
      'local_revision_id' => NULL,
    ];

    // Get the entity type and entity storage.
    $parsed_type = explode('--', $data['type']);
    $entity_type_id = $parsed_type[0];
    try {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    }
    catch (\Exception $exception) {
      $status_info = [
        'label' => $this->t('Unknown entity type'),
        'class' => 'entity-share-undefined',
        'info_id' => StateInformationInterface::INFO_ID_UNKNOWN,
        'local_entity_link' => NULL,
        'local_revision_id' => NULL,
      ];
      return $status_info;
    }

    // Check if an entity already exists.
    $existing_entities = $entity_storage
      ->loadByProperties(['uuid' => $data['id']]);

    if (empty($existing_entities)) {
      $status_info = [
        'label' => $this->t('New entity'),
        'class' => 'entity-share-new',
        'info_id' => StateInformationInterface::INFO_ID_NEW,
        'local_entity_link' => NULL,
        'local_revision_id' => NULL,
      ];
    }
    // An entity already exists.
    // Check if the entity type has a changed date.
    else {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $existing_entity */
      $existing_entity = array_shift($existing_entities);

      $resource_type = $this->resourceTypeRepository->get(
        $parsed_type[0],
        $parsed_type[1]
      );

      $changed_public_name = FALSE;
      if ($resource_type->hasField('changed')) {
        $changed_public_name = $resource_type->getPublicName('changed');
      }

      if (!empty($data['attributes'][$changed_public_name]) && method_exists($existing_entity, 'getChangedTime')) {
        $entity_changed_time = 0;
        // If the website is using backward compatible timestamps output.
        // @see https://www.drupal.org/node/2859657.
        // The value is casted in integer for
        // https://www.drupal.org/node/2837696.
        if (is_numeric($data['attributes'][$changed_public_name])) {
          $entity_changed_time = (int) $data['attributes'][$changed_public_name];
        }
        elseif ($changed_datetime = \DateTime::createFromFormat(\DateTime::RFC3339, $data['attributes'][$changed_public_name])) {
          $entity_changed_time = $changed_datetime->getTimestamp();
        }

        $entity_keys = $entity_storage
          ->getEntityType()
          ->getKeys();
        // Case of translatable entity.
        if (isset($entity_keys['langcode']) && !empty($entity_keys['langcode'])) {
          $entity_language_id = $data['attributes'][$resource_type->getPublicName($entity_keys['langcode'])];

          // Entity has the translation.
          if ($existing_entity->hasTranslation($entity_language_id)) {
            $existing_translation = $existing_entity->getTranslation($entity_language_id);
            $existing_entity_changed_time = $existing_translation->getChangedTime();

            // Existing entity.
            if ($entity_changed_time != $existing_entity_changed_time) {
              $status_info = [
                'label' => $this->t('Entities not synchronized'),
                'class' => 'entity-share-changed',
                'info_id' => StateInformationInterface::INFO_ID_CHANGED,
                'local_entity_link' => $existing_entity->toUrl(),
                'local_revision_id' => $existing_entity->getRevisionId(),
              ];
            }
            else {
              $status_info = [
                'label' => $this->t('Entities synchronized'),
                'class' => 'entity-share-up-to-date',
                'info_id' => StateInformationInterface::INFO_ID_SYNCHRONIZED,
                'local_entity_link' => $existing_entity->toUrl(),
                'local_revision_id' => $existing_entity->getRevisionId(),
              ];
            }
          }
          else {
            $status_info = [
              'label' => $this->t('New translation'),
              'class' => 'entity-share-new',
              'info_id' => StateInformationInterface::INFO_ID_NEW_TRANSLATION,
              'local_entity_link' => $existing_entity->toUrl(),
              'local_revision_id' => $existing_entity->getRevisionId(),
            ];
          }
        }
        // Case of untranslatable entity.
        else {
          $existing_entity_changed_time = $existing_entity->getChangedTime();

          // Existing entity.
          if ($entity_changed_time != $existing_entity_changed_time) {
            $status_info = [
              'label' => $this->t('Entities not synchronized'),
              'class' => 'entity-share-changed',
              'info_id' => StateInformationInterface::INFO_ID_CHANGED,
              'local_entity_link' => $existing_entity->toUrl(),
              'local_revision_id' => $existing_entity->getRevisionId(),
            ];
          }
          else {
            $status_info = [
              'label' => $this->t('Entities synchronized'),
              'class' => 'entity-share-up-to-date',
              'info_id' => StateInformationInterface::INFO_ID_SYNCHRONIZED,
              'local_entity_link' => $existing_entity->toUrl(),
              'local_revision_id' => $existing_entity->getRevisionId(),
            ];
          }
        }
      }
    }

    return $status_info;
  }

}
