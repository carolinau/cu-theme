<?php

/**
 * @file
 * Drush (< 9) integration for the entity_share_client module.
 */

declare(strict_types = 1);

use Drupal\entity_share_client\Drush8Io;

/**
 * Implements hook_drush_command().
 */
function entity_share_client_drush_command() {
  $items['entity-share-client-pull'] = [
    'description' => 'Pull the content of a channel of a remote website.',
    'core' => ['8+'],
    'aliases' => [],
    'arguments' => [
      'remote_id' => 'The remote website id to import from.',
      'channel_id' => 'The remote channel id to import.',
    ],
    'options' => [
      'update' => 'Use this option to fetch only new and updated entities.',
    ],
    'examples' => [
      'drush entity-share-client-pull content_hub articles_fr' => 'Import the channel articles_fr from the content_hub website',
      'drush entity-share-client-pull content_hub articles_fr --update' => 'Pull only new and changed entities from articles_fr channel.',
    ],
  ];

  return $items;
}

/**
 * Command callback.
 *
 * Pull the content of a channel of a remote website.
 *
 * @param string $remote_id
 *   The remote website id to import from.
 * @param string $channel_id
 *   The remote channel id to import.
 *
 * @return bool
 *   Return Drush error if an error has occurred.
 */
function drush_entity_share_client_pull($remote_id = '', $channel_id = '') {
  $args = func_get_args();
  if (count($args) < 2) {
    return drush_set_error('DRUSH_ARGUMENTS_ERROR', dt('Usage: drush entity-share-client-pull <remote_id> <channel_id>'));
  }

  try {
    // Make the magic happen.
    $update = drush_get_option('update');
    if ($update) {
      \Drupal::service('entity_share_client.cli')->ioPullUpdates($remote_id, $channel_id, new Drush8Io(), 'dt');
    }
    else {
      \Drupal::service('entity_share_client.cli')->ioPull($remote_id, $channel_id, new Drush8Io(), 'dt');
    }
  }
  catch (Exception $e) {
    return drush_set_error('DRUSH_EXECUTION_ERROR', $e->getMessage());
  }
}
