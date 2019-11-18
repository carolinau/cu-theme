<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Service;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_share_server\Entity\ChannelInterface;
use Drupal\entity_share_server\OperatorsHelper;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Class ChannelManipulator.
 *
 * @package Drupal\entity_share_server\Service
 */
class ChannelManipulator implements ChannelManipulatorInterface {

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
   * ChannelManipulator constructor.
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
  public function getQuery(ChannelInterface $channel) {
    $query = [];

    // In case of translatable entities. Add a filter on the langcode to
    // only get entities in the channel language.
    if ($channel->get('channel_langcode') != LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      $entity_type = $channel->get('channel_entity_type');
      $entity_keys = $this->entityTypeManager->getStorage($entity_type)
        ->getEntityType()
        ->getKeys();
      $resource_type = $this->resourceTypeRepository->get(
        $entity_type,
        $channel->get('channel_bundle')
      );
      $langcode_path = 'langcode';
      if (isset($entity_keys['langcode']) && !empty($entity_keys['langcode'])) {
        $langcode_path = $resource_type->getPublicName($entity_keys['langcode']);
      }

      $query['filter']['langcode-filter'] = [
        'condition' => [
          'path' => $langcode_path,
          'operator' => '=',
          'value' => $channel->get('channel_langcode'),
        ],
      ];
    }

    // Add groups.
    if (!is_null($channel->get('channel_groups'))) {
      foreach ($channel->get('channel_groups') as $group_id => $group) {
        $query['filter'][$group_id] = [
          'group' => [
            'conjunction' => $group['conjunction'],
          ],
        ];

        if (isset($group['memberof'])) {
          $query['filter'][$group_id]['group']['memberOf'] = $group['memberof'];
        }
      }
    }

    // Add filters.
    if (!is_null($channel->get('channel_filters'))) {
      foreach ($channel->get('channel_filters') as $filter_id => $filter) {
        $query['filter'][$filter_id] = [
          'condition' => [
            'path' => $filter['path'],
            'operator' => $filter['operator'],
          ],
        ];

        if (isset($filter['value'])) {
          // Multiple values operators.
          if (in_array($filter['operator'], OperatorsHelper::getMultipleValuesOperators())) {
            $query['filter'][$filter_id]['condition']['value'] = $filter['value'];
          }
          else {
            $query['filter'][$filter_id]['condition']['value'] = implode($filter['value']);
          }
        }

        if (isset($filter['memberof'])) {
          $query['filter'][$filter_id]['condition']['memberOf'] = $filter['memberof'];
        }
      }
    }

    // Add sorts.
    if (!is_null($channel->get('channel_sorts'))) {
      $sorts = $channel->get('channel_sorts');

      uasort($sorts, [SortArray::class, 'sortByWeightElement']);

      foreach ($sorts as $sort_id => $sort) {
        $query['sort'][$sort_id] = [
          'path' => $sort['path'],
          'direction' => $sort['direction'],
        ];
      }
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapping(ChannelInterface $channel) {
    $channel_entity_type = $channel->get('channel_entity_type');
    $channel_bundle = $channel->get('channel_bundle');

    $field_mapping = [];
    $field_keys = [
      'label',
      'changed',
    ];
    $entity_type = $this->entityTypeManager->getStorage($channel_entity_type)->getEntityType();
    $entity_keys = $entity_type->getKeys();
    $resource_type = $this->resourceTypeRepository->get(
      $channel_entity_type,
      $channel_bundle
    );
    foreach ($field_keys as $original_field_key) {
      $field_key = $original_field_key;
      if (isset($entity_keys[$field_key])) {
        $field_key = $entity_keys[$field_key];
      }
      if ($resource_type->hasField($field_key)) {
        $field_mapping[$original_field_key] = $resource_type->getPublicName($field_key);
      }
    }
    return $field_mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function getSearchConfiguration(ChannelInterface $channel) {
    $channel_entity_type = $channel->get('channel_entity_type');
    $channel_bundle = $channel->get('channel_bundle');

    $entity_type = $this->entityTypeManager->getStorage($channel_entity_type)->getEntityType();
    $entity_keys = $entity_type->getKeys();
    $resource_type = $this->resourceTypeRepository->get(
      $channel_entity_type,
      $channel_bundle
    );

    $search_configuration = [];
    if (isset($entity_keys['label']) && !empty($entity_keys['label'] && $resource_type->hasField($entity_keys['label']))) {
      $search_configuration['label'] = [
        'path' => $resource_type->getPublicName($entity_keys['label']),
        'label' => $this->t('Label'),
      ];
    }

    // Get the searches from configuration.
    if (!is_null($channel->get('channel_searches'))) {
      foreach ($channel->get('channel_searches') as $search_id => $search) {
        $search_configuration[$search_id] = [
          'path' => $search['path'],
          'label' => $search['label'],
        ];
      }
    }

    return $search_configuration;
  }

}
