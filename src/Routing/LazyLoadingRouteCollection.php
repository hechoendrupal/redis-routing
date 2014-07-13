<?php
/**
 * @file
 * Contains Drupal\redis_routing\Routing\LazyLoadingRouteCollection.
 */

namespace Drupal\redis_routing\Routing;

use Drupal\Core\Routing\LazyLoadingRouteCollection as BaseLazyLoading;
use Predis\Client;

class LazyLoadingRouteCollection extends BaseLazyLoading implements \Iterator
{
  /**
   * Creates a LazyLoadingRouteCollection instance.
   *
   * @param \Predis\Client $redis
   *   The redis connection.
   * @param string $prefix
   *   (optional) The prefix for redis routing storage.
   */
  public function __construct(Client $redis, $prefix='router')
  {
    $this->redis = $redis;
    $this->prefix = $prefix;
    $this->routes = $this->redis->hgetall('router:patterns');
  }

  /**
   * {@inheritdoc}
   */
  public function loadNextElements($offset)
  {
    $this->elements = [];
    $route_names = array_slice(array_values($this->routes), $offset, 50);

    $result = [];
    foreach ($route_names as $name) {
      $result[] = $this->redis->hmget('router:'.$name,['name','route']);
    }

    $routes = [];
    foreach ($result as $route) {
      $name = $route[0];
      $routes[$name] = unserialize($route[1]);
    }

    $this->elements = $routes;
  }

  /**
   * {@inheritdoc}
   */
  public function count()
  {
    if (!isset($this->count)) {
      $this->count = (int) count($this->routes);
    }
    return $this->count;
  }
}
