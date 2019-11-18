<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\diff\Controller\PluginRevisionController;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\Entity\RemoteInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Diff support routes.
 */
class DiffController extends PluginRevisionController {

  /**
   * The remote manager service.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  private $remoteManager;

  /**
   * The request service.
   *
   * @var \Drupal\entity_share_client\Service\RequestServiceInterface
   */
  private $requestService;

  /**
   * The jsonapi helper.
   *
   * @var \Drupal\entity_share_client\Service\JsonapiHelperInterface
   */
  private $jsonapiHelper;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\ResettableStackedRouteMatchInterface
   */
  private $routeMatch;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  private $dateFormatter;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->remoteManager = $container->get('entity_share_client.remote_manager');
    $instance->requestService = $container->get('entity_share_client.request');
    $instance->jsonapiHelper = $container->get('entity_share_client.jsonapi_helper');
    $instance->routeMatch = $container->get('current_route_match');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->resourceTypeRepository = $container->get('jsonapi.resource_type.repository');
    return $instance;
  }

  /**
   * Returns a table showing the differences between local and remote entities.
   *
   * @param int $left_revision
   *   The revision id of the local entity.
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote from which the entity is from.
   * @param string $channel_id
   *   The channel ID from which the entity is from. Used to handle language.
   * @param string $uuid
   *   The UUID of the entity.
   *
   * @return array
   *   Table showing the diff between the local and remote entities.
   */
  public function compareEntities($left_revision, RemoteInterface $remote, $channel_id, $uuid) {
    // Reload the remote to have config overrides applied.
    $remote = $this->entityTypeManager()
      ->getStorage('remote')
      ->load($remote->id());
    $channels_infos = $this->remoteManager->getChannelsInfos($remote);

    // Get the left/local revision.
    $entity_type_id = $channels_infos[$channel_id]['channel_entity_type'];
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    $left_revision = $storage->loadRevision($left_revision);

    // Get the right/remote revision.
    $url = $channels_infos[$channel_id]['url'];
    $parsed_url = UrlHelper::parse($url);
    $query = $parsed_url['query'];
    $query['filter']['uuid-filter'] = [
      'condition' => [
        'path' => 'id',
        'operator' => 'IN',
        'value' => array_values([$uuid]),
      ],
    ];
    $query = UrlHelper::buildQuery($query);
    $prepared_url = $parsed_url['path'] . '?' . $query;

    $http_client = $this->remoteManager->prepareJsonApiClient($remote);
    $response = $this->requestService->request($http_client, 'GET', $prepared_url);
    $json = Json::decode((string) $response->getBody());

    $entity_type = $storage->getEntityType();
    $entity_keys = $entity_type->getKeys();

    $resource_type = $this->resourceTypeRepository->get(
      $entity_type_id,
      $left_revision->bundle()
    );
    $id_public_name = $resource_type->getPublicName($entity_keys['id']);

    // There will be only one result.
    foreach (EntityShareUtility::prepareData($json['data']) as $entity_data) {
      // Force the remote entity id to be the same as the local entity otherwise
      // the diff is not helpful.
      $entity_data['attributes'][$id_public_name] = $left_revision->id();
      $right_revision = $this->jsonapiHelper->extractEntity($entity_data);
    }

    $build = $this->compareEntityRevisions($this->routeMatch, $left_revision, $right_revision, 'split_fields');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function compareEntityRevisions(RouteMatchInterface $route_match, ContentEntityInterface $left_revision, ContentEntityInterface $right_revision, $filter) {
    $entity = $left_revision;
    // Get language from the entity context.
    $langcode = $entity->language()->getId();

    // Get left and right revision in current language.
    $left_revision = $left_revision->getTranslation($langcode);
    $right_revision = $right_revision->getTranslation($langcode);

    $build = [
      '#title' => $this->t('Changes to %title', ['%title' => $entity->label()]),
      'header' => [
        '#prefix' => '<header class="diff-header">',
        '#suffix' => '</header>',
      ],
      'controls' => [
        '#prefix' => '<div class="diff-controls">',
        '#suffix' => '</div>',
      ],
    ];

    // Perform comparison only if both entity revisions loaded successfully.
    if ($left_revision != FALSE && $right_revision != FALSE) {
      // Build the diff comparison with the plugin.
      if ($plugin = $this->diffLayoutManager->createInstance($filter)) {
        $build = array_merge_recursive($build, $plugin->build($left_revision, $right_revision, $entity));
        unset($build['header']);
        unset($build['controls']);

        // Changes diff table header.
        $left_changed = '';
        if (method_exists($left_revision, 'getChangedTime')) {
          $left_changed = $this->dateFormatter->format($left_revision->getChangedTime(), 'short');
        }
        $build['diff']['#header'][0]['data']['#markup'] = $this->t('Local entity: @changed', [
          '@changed' => $left_changed,
        ]);
        $right_changed = '';
        if (method_exists($right_revision, 'getChangedTime')) {
          $right_changed = $this->dateFormatter->format($right_revision->getChangedTime(), 'short');
        }
        $build['diff']['#header'][1]['data']['#markup'] = $this->t('Remote entity: @changed', [
          '@changed' => $right_changed,
        ]);

        $build['diff']['#prefix'] = '<div class="diff-responsive-table-wrapper">';
        $build['diff']['#suffix'] = '</div>';
        $build['diff']['#attributes']['class'][] = 'diff-responsive-table';
      }
    }

    $build['#attached']['library'][] = 'diff/diff.general';
    return $build;
  }

}
