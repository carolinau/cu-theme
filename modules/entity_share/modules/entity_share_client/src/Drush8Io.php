<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client;

use Drush\Log\LogLevel;

/**
 * Class Drush8Io.
 *
 * This is a stand in for \Symfony\Component\Console\Style\StyleInterface with
 * drush 8 so that we don't need to depend on symfony components.
 */
// @codingStandardsIgnoreStart
class Drush8Io {

  public function confirm($text) {
    return drush_confirm($text);
  }

  public function success($text) {
    drush_log($text, LogLevel::SUCCESS);
  }

  public function error($text) {
    drush_log($text, LogLevel::ERROR);
  }

  public function text($text) {
    drush_log($text, LogLevel::NOTICE);
  }

}
// @codingStandardsIgnoreEnd
