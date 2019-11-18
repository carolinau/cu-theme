<?php

declare(strict_types = 1);

namespace Drupal\entity_share;

/**
 * Contains helper methods for Entity share.
 */
class EntityShareUtility {

  /**
   * Uniformize JSON data in case of single value.
   *
   * @param array $data
   *   The JSON data.
   *
   * @return array
   *   An array of data.
   */
  public static function prepareData(array $data) {
    if (self::isNumericArray($data)) {
      return $data;
    }
    else {
      return [$data];
    }
  }

  /**
   * Check if a array is numeric.
   *
   * @param array $array
   *   The array to check.
   *
   * @return bool
   *   TRUE if the array is numeric. FALSE in case of associative array.
   */
  public static function isNumericArray(array $array) {
    foreach ($array as $a => $b) {
      if (!is_int($a)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
