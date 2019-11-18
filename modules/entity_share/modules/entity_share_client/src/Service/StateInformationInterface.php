<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

/**
 * Remote manager interface methods.
 */
interface StateInformationInterface {

  /**
   * The info id in the case of an undefined state.
   */
  const INFO_ID_UNDEFINED = 'undefined';

  /**
   * The info id in the case of an unknown entity type.
   */
  const INFO_ID_UNKNOWN = 'unknown';

  /**
   * The info id in the case of a new entity.
   */
  const INFO_ID_NEW = 'new';

  /**
   * The info id in the case of a new entity translation.
   */
  const INFO_ID_NEW_TRANSLATION = 'new_translation';

  /**
   * The info id in the case of a changed entity or translation.
   */
  const INFO_ID_CHANGED = 'changed';

  /**
   * The info id in the case of a synchronized entity or translation.
   */
  const INFO_ID_SYNCHRONIZED = 'synchronized';

  /**
   * Check if an entity already exists or not and get status info.
   *
   * Default implementation is to compare revision timestamp.
   *
   * @param array $data
   *   The data of a single entity from the JSON:API payload.
   *
   * @return array
   *   Returns an array of info:
   *     - label: the label to display.
   *     - class: to add a class on a row.
   *     - info_id: an identifier of the status info.
   *     - local_entity_link: the link of the local entity if it exists.
   *     - local_revision_id: the revision ID of the local entity if it exists.
   */
  public function getStatusInfo(array $data);

}
