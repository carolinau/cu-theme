<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_share_client\Entity\RemoteInterface;

/**
 * Jsonapi helper interface methods.
 */
interface JsonapiHelperInterface {

  /**
   * Prepare entities from an URI to request.
   *
   * @param array $json_data
   *   An array of data send by the JSON:API.
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The selected remote.
   * @param string $channel_id
   *   The selected channel id.
   *
   * @return array
   *   The array of options for the tableselect form type element.
   */
  public function buildEntitiesOptions(array $json_data, RemoteInterface $remote, $channel_id);

  /**
   * Helper function to unserialize an entity from the JSON:API response.
   *
   * @param array $data
   *   An array of data.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   An unserialize entity.
   */
  public function extractEntity(array $data);

  /**
   * Create or update the entity reference field values of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to update.
   * @param array $data
   *   An array of data.
   */
  public function updateRelationships(ContentEntityInterface $entity, array $data);

  /**
   * Create or update the entity reference field values of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to update.
   * @param array $data
   *   An array of data. Can be modified to change the URI if needed.
   */
  public function handlePhysicalFiles(ContentEntityInterface $entity, array &$data);

  /**
   * Use data from the JSON:API to import content.
   *
   * @param array $entity_list_data
   *   An array of data from a JSON:API endpoint.
   *
   * @return int[]
   *   The list of entity ids imported.
   */
  public function importEntityListData(array $entity_list_data);

  /**
   * Set the remote to get content from.
   *
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote website to get content from.
   */
  public function setRemote(RemoteInterface $remote);

  /**
   * Allow to clear imported entities counter.
   *
   * So the same entity can be reimported multiple times during the same PHP
   * process.
   *
   * Without argument, all imported entities counter are deleted.
   *
   * If a langcode is passed, all imported entities counter in this langcode are
   * deleted.
   *
   * If an entity UUID is passed, the imported entity counter is deleted for all
   * languages.
   *
   * If a langcode and an entity UUID are passed, the imported entity counter is
   * deleted for the specified language.
   *
   * @param string $langcode
   *   The langcode for which to clear the imported entities.
   * @param string $entity_uuid
   *   The UUID of the entity to clear the langcode for.
   */
  public function clearImportedEntities($langcode = '', $entity_uuid = '');

}
