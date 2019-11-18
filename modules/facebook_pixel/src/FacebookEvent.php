<?php

namespace Drupal\facebook_pixel;

use Drupal\Core\Session\SessionManager;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Class FacebookEvent.
 *
 * @package Drupal\facebook_pixel
 */
class FacebookEvent implements FacebookEventInterface {

  /**
   * Static events array for anonymous users.
   *
   * @var array
   */
  protected static $events = [];

  /**
   * Private temporary storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $privateTempStore;

  /**
   * Session manager container.
   *
   * @var \Drupal\Core\Session\SessionManager
   */
  protected $sessionManager;

  /**
   * FacebookEvent constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStore
   *   The temp store factory service.
   * @param \Drupal\Core\Session\SessionManager $sessionManager
   *   The session manager service.
   */
  public function __construct(PrivateTempStoreFactory $privateTempStore, SessionManager $sessionManager) {
    $this->privateTempStore = $privateTempStore->get('user');
    $this->sessionManager = $sessionManager;
  }

  /**
   * Register an event.
   *
   * @param string $event
   *   The event name.
   * @param string|array $data
   *   The event data.
   * @param bool $start_session
   *   Force initialize a session.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function addEvent($event, $data = '', $start_session = FALSE) {
    // Determine if we should use session or static storage.
    if ((!empty($this->sessionManager) && $this->sessionManager->isStarted()) || $start_session) {
      $this->addSessionEvent($event, $data);
    }
    else {
      $this->addAnonymousEvent($event, $data);
    }
  }

  /**
   * Adds an event for anonymous users.
   *
   * @param string $event
   *   The event name.
   * @param string|array $data
   *   The event data.
   */
  protected function addAnonymousEvent($event, $data) {
    self::$events[] = [
      'event' => $event,
      'data' => $data,
    ];
  }

  /**
   * Adds an event for sessioned users.
   *
   * @param string $event
   *   The event type.
   * @param string|array $data
   *   The data to send with the event.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function addSessionEvent($event, $data = '') {
    $storage = [];
    $storage += (array) $this->privateTempStore->get('facebook_pixel');
    $storage[] = [
      'event' => $event,
      'data' => $data,
    ];
    $this->privateTempStore->set('facebook_pixel', $storage);
  }

  /**
   * Get all registered events.
   *
   * @return array
   *   An array of registered events.
   */
  public function getEvents() {
    $events = self::$events;
    if (!empty($this->sessionManager) && $this->sessionManager->isStarted()) {
      $events += $this->getSessionEvents();
    }
    return array_unique($events, SORT_REGULAR);
  }

  /**
   * Fetch events when a user session exists.
   *
   * @return array
   *   The registered session events.
   */
  protected function getSessionEvents() {
    $events = (array) $this->privateTempStore->get('facebook_pixel');
    $this->flushEvents();
    return $events;
  }

  /**
   * Determines if a user session has been established.
   *
   * @return bool
   *   If a user has an established session.
   */
  protected function hasSession() {
    return !empty($this->sessionManager) && $this->sessionManager->isStarted();
  }

  /**
   * Delete the temp storage object.
   */
  protected function flushEvents() {
    try {
      $this->privateTempStore->delete('facebook_pixel');
    }
    catch (\Exception $ex) {
      // No action necessary.
    }
  }

}
