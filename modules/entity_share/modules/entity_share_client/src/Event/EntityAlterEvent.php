<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Event;

use Symfony\Component\EventDispatcher\Event;
use Drupal\core\Entity\EntityInterface;
use Drupal\entity_share_client\Entity\RemoteInterface;

/**
 * Defines an event for altering the entity.
 */
class EntityAlterEvent extends Event {

  const EVENT_NAME = 'entity_share_client.entity_alter';

  /**
   * The Entity object.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The remote entity.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface
   */
  protected $remote;

  /**
   * EntityAlterEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being manipulated.
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote entity.
   */
  public function __construct(EntityInterface $entity, RemoteInterface $remote) {
    $this->entity = $entity;
    $this->remote = $remote;
  }

  /**
   * Returns the entity object.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity being manipulated.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * The remote entity.
   *
   * @return \Drupal\entity_share_client\Entity\RemoteInterface
   *   The remote.
   */
  public function getRemote() {
    return $this->remote;
  }

  /**
   * Sets the Entity object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being manipulated.
   */
  public function setEntity(EntityInterface $entity) {
    $this->entity = $entity;
  }

}
