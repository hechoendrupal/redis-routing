<?php
/**
 * @file
 * Contains Drupal\redis_routing\RedisRoutingServiceProvider.
 */
namespace Drupal\redis_routing;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

class RedisRoutingServiceProvider implements ServiceProviderInterface
{
  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container)
  {
    // Add a compiler pass to change Routing services
    $container->addCompilerPass(new RedisRoutingServiceCompilerPass());
  }
}
