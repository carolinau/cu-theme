<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\entity_share_client\Entity\EntityImportStatus;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Import status entities.
 */
class EntityImportStatusListBuilder extends EntityListBuilder {

  /**
   * The format for the import time.
   *
   * Long format, with seconds.
   */
  const IMPORT_DATE_FORMAT = 'F j, Y - H:i:s';

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new UserListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    DateFormatterInterface $date_formatter,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['entity_uuid'] = $this->t('Entity UUID');
    $header['entity_id'] = $this->t('Entity ID');
    $header['langcode'] = $this->t('Language');
    $header['entity_label'] = $this->t('Link to entity');
    $header['entity_type_id'] = $this->t('Entity type');
    $header['entity_bundle'] = $this->t('Bundle');
    $header['remote_website'] = $this->t('Remote');
    $header['channel_id'] = $this->t('Channel');
    $header['last_import'] = $this->t('Last import');
    $header['policy'] = $this->t('Policy');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['id'] = $entity->id();
    // Load the imported entity.
    $imported_entity_storage = $this->entityTypeManager
      ->getStorage($entity->entity_type_id->value);
    $imported_entity = $imported_entity_storage->load($entity->entity_id->value);
    // Basic keys of imported entity.
    $row['entity_uuid'] = $entity->entity_uuid->value;
    $row['entity_id'] = $entity->entity_id->value;
    $row['langcode'] = $this->languageManager->getLanguage($entity->langcode->value)->getName();
    // Label and link to entity should respect the language.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $imported_entity_translation */
    $imported_entity_translation = $imported_entity->getTranslation($entity->langcode->value);
    try {
      $row['entity_label'] = $imported_entity_translation->toLink($imported_entity_translation->label());
    }
    catch (UndefinedLinkTemplateException $exception) {
      $row['entity_label'] = $imported_entity_translation->label();
    }
    // Label of entity type.
    $row['entity_type_id'] = $imported_entity_storage->getEntityType()->getLabel();
    // Imported entity's bundle.
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity->entity_type_id->value);
    $row['entity_bundle'] = $bundle_info[$entity->entity_bundle->value]['label'] ?? $entity->entity_bundle->value;
    // Remote website.
    $remote = $this->entityTypeManager
      ->getStorage('remote')
      ->load($entity->remote_website->value);
    $row['remote_website'] = $remote->label();
    // Machine name of the import channel.
    $row['channel_id'] = $entity->channel_id->value;
    // Last import time.
    $row['last_import'] = $this->dateFormatter->format($entity->getLastImport(), 'custom', self::IMPORT_DATE_FORMAT);
    // Label of the import policy (or raw value if illegal).
    $available_policies = EntityImportStatus::getAvailablePolicies();
    $row['policy'] = $available_policies[$entity->getPolicy()] ?? $entity->getPolicy();

    return $row + parent::buildRow($entity);
  }

}
