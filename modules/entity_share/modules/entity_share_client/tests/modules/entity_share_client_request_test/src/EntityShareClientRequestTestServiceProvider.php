<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client_request_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Class EntityShareClientRequestTestServiceProvider.
 */
class EntityShareClientRequestTestServiceProvider extends ServiceProviderBase {

  /**
   * Modifies existing service definitions.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The ContainerBuilder whose service definitions can be altered.
   */
  public function alter(ContainerBuilder $container) {
    $test_request_service_definition = $container->getDefinition('entity_share_client_request_test.request');
    $container->setDefinition('entity_share_client.request', $test_request_service_definition);
  }

}
