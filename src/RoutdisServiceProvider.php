<?php
/**
 * @file
 * Content Drupal\routdis\RoutdisServiceProvider.
 */
namespace Drupal\routdis;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

class RoutdisServiceProvider implements ServiceProviderInterface 
{
  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container)
  {
    // Add a compiler pass for adding Normalizers and Encoders to Serializer.
    $container->addCompilerPass(new RoutdisServiceCompilerPass());
  }
}