<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Service;

use Drupal\entity_share_server\Entity\ChannelInterface;

/**
 * Channel manipulators interface methods.
 */
interface ChannelManipulatorInterface {

  /**
   * Generate URL query.
   *
   * @param \Drupal\entity_share_server\Entity\ChannelInterface $channel
   *   The channel entity.
   *
   * @return array
   *   The query options to use to request JSON:API.
   */
  public function getQuery(ChannelInterface $channel);

  /**
   * Get field mapping.
   *
   * @param \Drupal\entity_share_server\Entity\ChannelInterface $channel
   *   The channel entity.
   *
   * @return array
   *   The field mapping used for sorting.
   */
  public function getFieldMapping(ChannelInterface $channel);

  /**
   * Get field mapping.
   *
   * @param \Drupal\entity_share_server\Entity\ChannelInterface $channel
   *   The channel entity.
   *
   * @return array
   *   The field mapping used for text search.
   */
  public function getSearchConfiguration(ChannelInterface $channel);

}
