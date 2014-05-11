<?php
/**
 * File content
 * Drupal\routdis\Routdis\ServiceCompilerPass.
 */
namespace Drupal\routdis;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class RoutdisServiceCompilerPass implements CompilerPassInterface
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
    $dumper->setClass('Drupal\routdis\Routing\MatcherDumper');
    $dumper->setArguments([
      new Reference('routdis.redis'),
      new Reference('state')
    ]);

    // replace route provider
    $provider = $container->getDefinition('router.route_provider');
    $provider->setClass('Drupal\routdis\Routing\RouteProvider');
    $provider->setArguments([
      new Reference('routdis.redis'),
      new Reference('router.builder'),
      new Reference('state')
    ]);
    $provider->setTags([
      'name' => 'event_subscriber'
    ]);
  }
}
