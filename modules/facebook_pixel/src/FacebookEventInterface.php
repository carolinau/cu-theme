<?php

namespace Drupal\facebook_pixel;

/**
 * Interface FacebookEventInterface.
 *
 * @package Drupal\facebook_pixel
 */
interface FacebookEventInterface {

  /**
   * Register an event.
   *
   * @param string $event
   *   The event name.
   * @param mixed $data
   *   The event data.
   */
  public function addEvent($event, $data);

  /**
   * Get the facebook events.
   */
  public function getEvents();

}
