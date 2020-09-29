<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\RuntimeImportContext;

/**
 * Import block contents from block fields.
 *
 * @ImportProcessor(
 *   id = "block_field_block_content_importer",
 *   label = @Translation("Block field block content"),
 *   description = @Translation("Import block contents from block fields."),
 *   stages = {
 *     "prepare_importable_entity_data" = 20,
 *   },
 *   locked = false,
 * )
 */
class BlockFieldBlockContentImporter extends EntityReference {

  /**
   * {@inheritdoc}
   */
  public function prepareImportableEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
    // Parse entity data to extract urls to get block content from block
    // field. And remove this info.
    if (isset($entity_json_data['attributes']) && is_array($entity_json_data['attributes'])) {
      foreach ($entity_json_data['attributes'] as $field_name => $field_data) {
        if (is_array($field_data)) {
          if (EntityShareUtility::isNumericArray($field_data)) {
            foreach ($field_data as $delta => $value) {
              if (isset($value['block_content_href'])) {
                $this->importUrl($runtime_import_context, $value['block_content_href']);
                unset($entity_json_data['attributes'][$field_name][$delta]['block_content_href']);
              }
            }
          }
          elseif (isset($field_data['block_content_href'])) {
            $this->importUrl($runtime_import_context, $field_data['block_content_href']);
            unset($entity_json_data['attributes'][$field_name]['block_content_href']);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function importUrl(RuntimeImportContext $runtime_import_context, $url) {
    // In the case of block field, if the block content entity is already
    // present on the website, there is nothing to do.
    if ($this->currentRecursionDepth == $this->configuration['max_recursion_depth']) {
      return [];
    }

    return parent::importUrl($runtime_import_context, $url);
  }

}
