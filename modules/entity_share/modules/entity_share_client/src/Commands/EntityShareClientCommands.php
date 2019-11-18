<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Commands;

use Drupal\entity_share_client\Service\EntityShareClientCliService;
use Drush\Commands\DrushCommands;

/**
 * Class EntityShareClientCommands.
 *
 * This is the Drush 9 commands.
 *
 * @package Drupal\entity_share_client\Commands
 */
class EntityShareClientCommands extends DrushCommands {

  /**
   * The interoperability cli service.
   *
   * @var \Drupal\entity_share_client\Service\EntityShareClientCliService
   */
  protected $cliService;

  /**
   * EntityShareClientCommands constructor.
   *
   * @param \Drupal\entity_share_client\Service\EntityShareClientCliService $cliService
   *   The CLI service which allows interoperability.
   */
  public function __construct(EntityShareClientCliService $cliService) {
    $this->cliService = $cliService;
  }

  /**
   * Pull a channel from a remote website.
   *
   * @param string $remote_id
   *   The remote website id to import from.
   * @param string $channel_id
   *   The remote channel id to import.
   * @param array $options
   *   Additional Drush 9 command options.
   *
   * @command entity-share-client:pull
   * @options update Optional --update argument which denotes script to fetch only new and updated entities.
   * @usage drush entity-share-client:pull remote_id channel_id --update
   *   Pull a channel from a remote website. Add --update option to fetch only
   *   new and updated entities.
   */
  public function pullChannel($remote_id = '', $channel_id = '', array $options = ['update' => FALSE]) {
    if ($options['update']) {
      $this->cliService->ioPullUpdates($remote_id, $channel_id, $this->io(), 'dt');
    }
    else {
      $this->cliService->ioPull($remote_id, $channel_id, $this->io(), 'dt');
    }
  }

}
