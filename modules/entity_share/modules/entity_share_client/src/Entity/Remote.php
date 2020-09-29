<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Remote entity.
 *
 * @ConfigEntityType(
 *   id = "remote",
 *   label = @Translation("Remote"),
 *   handlers = {
 *     "list_builder" = "Drupal\entity_share_client\RemoteListBuilder",
 *     "form" = {
 *       "add" = "Drupal\entity_share_client\Form\RemoteForm",
 *       "edit" = "Drupal\entity_share_client\Form\RemoteForm",
 *       "delete" = "Drupal\entity_share_client\Form\RemoteDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\entity_share_client\RemoteHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "remote",
 *   admin_permission = "administer_remote_entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "url",
 *     "basic_auth_username",
 *     "basic_auth_password",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/services/entity_share/remote/{remote}",
 *     "add-form" = "/admin/config/services/entity_share/remote/add",
 *     "edit-form" = "/admin/config/services/entity_share/remote/{remote}/edit",
 *     "delete-form" = "/admin/config/services/entity_share/remote/{remote}/delete",
 *     "collection" = "/admin/config/services/entity_share/remote"
 *   }
 * )
 */
class Remote extends ConfigEntityBase implements RemoteInterface {

  /**
   * The Remote ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Remote label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Remote URL.
   *
   * @var string
   */
  protected $url;

  /**
   * The Remote basic auth username.
   *
   * @var string
   */
  protected $basic_auth_username;

  /**
   * The Remote basic auth password.
   *
   * @var string
   */
  protected $basic_auth_password;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Ensure no trailing slash at the end of the remote URL.
    $remote_url = $this->get('url');
    $matches = [];
    if (!empty($remote_url) && preg_match('/(.*)\/$/', $remote_url, $matches)) {
      $this->set('url', $matches[1]);
    }
  }

}
