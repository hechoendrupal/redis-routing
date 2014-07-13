<?php
/**
 * @file
 * Contains Drupal\redis_routing\ServiceCompilerPass.
 */
namespace Drupal\redis_routing;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class RedisRoutingServiceCompilerPass implements CompilerPassInterface
{
  /**
   * Change services in @service_container
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container to process.
   */
  public function process(ContainerBuilder $container)
  {
    // replace router dumper
    $dumper = $container->getDefinition('router.dumper');
    $dumper->setClass('Drupal\redis_routing\Routing\MatcherDumper');
    $dumper->setArguments([
      new Reference('predis.connnection'),
      new Reference('state'),
    ]);

    // replace route provider
    $provider = $container->getDefinition('router.route_provider');
    $provider->setClass('Drupal\redis_routing\Routing\RouteProvider');
    $provider->setArguments([
      new Reference('predis.connnection'),
      new Reference('router.builder'),
      new Reference('state'),
    ]);
    $provider->setTags([
      ['name' => ['event_subscriber']],
    ]);
  }
}
