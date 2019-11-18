<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Event;

use Symfony\Component\EventDispatcher\Event;
use Drupal\entity_share_client\Entity\RemoteInterface;

/**
 * Defines an event for altering the entity.
 */
class EntityListDataAlterEvent extends Event {

  const EVENT_NAME = 'entity_share_client.entity_data_alter';

  /**
   * The Entity object.
   *
   * @var array
   */
  protected $entityListData = [];

  /**
   * The remote entity.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface
   */
  protected $remote;

  /**
   * EntityDataAlterEvent constructor.
   *
   * @param array $entity_list_data
   *   The entity list data.
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote entity.
   */
  public function __construct(array $entity_list_data, RemoteInterface $remote) {
    $this->entityListData = $entity_list_data;
    $this->remote = $remote;
  }

  /**
   * Returns the entity list data.
   *
   * @return array
   *   The entity list data.
   */
  public function getEntityListData() {
    return $this->entityListData;
  }

  /**
   * Sets the entity list data.
   *
   * @param array $entity_list_data
   *   The entity list data.
   */
  public function setEntityListData(array $entity_list_data) {
    $this->entityListData = $entity_list_data;
  }

  /**
   * THe remote entity.
   *
   * @return \Drupal\entity_share_client\Entity\RemoteInterface
   *   The remote entity.
   */
  public function getRemote() {
    return $this->remote;
  }

}
