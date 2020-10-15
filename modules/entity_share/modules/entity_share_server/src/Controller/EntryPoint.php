<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\entity_share_server\Event\ChannelListEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller to generate list of channels URLs.
 */
class EntryPoint extends ControllerBase {

  /**
   * The channel manipulator.
   *
   * @var \Drupal\entity_share_server\Service\ChannelManipulatorInterface
   */
  protected $channelManipulator;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->channelManipulator = $container->get('entity_share_server.channel_manipulator');
    $instance->resourceTypeRepository = $container->get('jsonapi.resource_type.repository');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * Controller to list all the resources.
   */
  public function index() {
    $self = Url::fromRoute('entity_share_server.resource_list')
      ->setOption('absolute', TRUE)
      ->toString();
    $urls = [
      'self' => $self,
    ];
    $data = [
      'channels' => [],
      'field_mappings' => $this->getFieldMappings(),
    ];

    $uuid = 'anonymous';
    if ($this->currentUser()->isAuthenticated()) {
      // Load the user to ensure with have a user entity.
      /** @var \Drupal\user\UserInterface $account */
      $account = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
      if (!is_null($account)) {
        $uuid = $account->uuid();
      }
    }

    /** @var \Drupal\entity_share_server\Entity\ChannelInterface[] $channels */
    $channels = $this->entityTypeManager()
      ->getStorage('channel')
      ->loadMultiple();

    $languages = $this->languageManager()->getLanguages(LanguageInterface::STATE_ALL);
    foreach ($channels as $channel) {
      // Check access for this user.
      if (in_array($uuid, $channel->get('authorized_users'))) {
        $channel_entity_type = $channel->get('channel_entity_type');
        $channel_bundle = $channel->get('channel_bundle');
        $channel_langcode = $channel->get('channel_langcode');
        $route_name = sprintf('jsonapi.%s--%s.collection', $channel_entity_type, $channel_bundle);
        $url = Url::fromRoute($route_name)
          ->setOption('language', $languages[$channel_langcode])
          ->setOption('absolute', TRUE)
          ->setOption('query', $this->channelManipulator->getQuery($channel));

        // Prepare an URL to get only the UUIDs.
        $url_uuid = clone($url);
        $query = $url_uuid->getOption('query');
        $query = (!is_null($query)) ? $query : [];
        $url_uuid->setOption('query',
          $query + [
            'fields' => [
              $channel_entity_type . '--' . $channel_bundle => 'changed',
            ],
          ]
        );

        $data['channels'][$channel->id()] = [
          'label' => $channel->label(),
          'url' => $url->toString(),
          'url_uuid' => $url_uuid->toString(),
          'channel_entity_type' => $channel_entity_type,
          'channel_bundle' => $channel_bundle,
          'search_configuration' => $this->channelManipulator->getSearchConfiguration($channel),
        ];
      }
    }

    // Collect other channel definitions.
    $event = new ChannelListEvent($data);
    $this->eventDispatcher->dispatch(ChannelListEvent::EVENT_NAME, $event);

    return new JsonResponse([
      'data' => $event->getChannelList(),
      'links' => $urls,
    ]);
  }

  /**
   * Get all field mappings so clients are aware of the server configuration.
   *
   * [
   *   'entity_type_id' => [
   *     'bundle' => [
   *       'internal name' => 'public name',
   *     ],
   *   ],
   * ];
   *
   * @return array
   *   An array as explained in the text above.
   */
  protected function getFieldMappings() {
    $mapping = [];
    $definitions = $this->entityTypeManager()->getDefinitions();
    $resource_types = $this->resourceTypeRepository->all();

    foreach ($resource_types as $resource_type) {
      $entity_type_id = $resource_type->getEntityTypeId();

      // Do not expose config entities and user, as we do not manage them.
      if ($entity_type_id == 'user' || $definitions[$entity_type_id]->getGroup() != 'content') {
        continue;
      }

      $bundle = $resource_type->getBundle();
      $resource_type_fields = $resource_type->getFields();
      foreach ($resource_type_fields as $resource_type_field) {
        $mapping[$entity_type_id][$bundle][$resource_type_field->getInternalName()] = $resource_type_field->getPublicName();
      }
    }
    return $mapping;
  }

}
