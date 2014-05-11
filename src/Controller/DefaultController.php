<?php
/**
 * File content
 * Drupal\routdis\Controller\DefautlController.
 */
namespace Drupal\routdis\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\routdis\Database\Redis;

class DefaultController extends ControllerBase implements ContainerInjectionInterface 
{
  /**
  * @var Drupal\routdis\Database\Redis
  */
  protected $redis;

  public function __construct(Redis $routdis_redis)
  {
    $this->redis = $routdis_redis->getConnection();
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('routdis.redis')
    );
  }

  /**
   * hello
   * @param  string $name
   * @return string
   */
  public function hello() 
  {
    #ladybug_dump($this->redis);
    
  }

}
