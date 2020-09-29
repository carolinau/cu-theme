<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Commands;

use Drupal\entity_share_client\Service\EntityShareClientCliService;
use Drush\Commands\DrushCommands;

/**
 * Class EntityShareClientCommands.
 *
 * These are the Drush >= 9 commands.
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
   * @param array $options
   *   Additional command options.
   *
   * @command entity-share-client:pull
   * @options remote-id Required. The remote website id to import from.
   * @options channel-id Required. The remote channel id to import.
   * @options import-config-id Required. The import config id to import with.
   * @usage drush entity-share-client:pull --remote-id=site_1 --channel-id=articles_en --import-config-id=default
   *   Pull a channel from a remote website. The "Include count in collection
   *   queries" option should be enabled on the server website. This option is
   *   provided by the JSON:API Extras module.
   */
  public function pullChannel(array $options = [
    'remote-id' => '',
    'channel-id' => '',
    'import-config-id' => '',
  ]) {
    // Validate options.
    $required_options = [
      'remote-id',
      'channel-id',
      'import-config-id',
    ];
    $missing_option = FALSE;
    foreach ($required_options as $required_option) {
      if (empty($options[$required_option])) {
        $missing_option = TRUE;
        $this->logger()->error(dt('Missing required option @option.', [
          '@option' => $required_option,
        ]));
      }
    }

    if (!$missing_option) {
      $this->cliService->ioPull($options['remote-id'], $options['channel-id'], $options['import-config-id'], $this->io(), 'dt');
    }
  }

}
