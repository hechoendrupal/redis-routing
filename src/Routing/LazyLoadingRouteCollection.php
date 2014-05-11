<?php

/**
 * @file
 * Content Drupal\routdis\Routing\LazyLoadingRouteCollection.
 */

namespace Drupal\routdis\Routing;

use Drupal\Core\Routing\LazyLoadingRouteCollection as BaseLazyLoading;
use Predis\Client;
use Iterator;

class LazyLoadingRouteCollection extends BaseLazyLoading implements Iterator
{

	/**
   * Creates a LazyLoadingRouteCollection instance.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param string $table
   *   (optional) The table to retrieve the route information.
   */
  public function __construct(Client $redis, $routes) {
    $this->redis = $redis;
    $this->routes = $routes;
  }

	/**
   * Loads the next routes into the elements array.
   *
   * @param int $offset
   *   The offset used in the db query.
   */
  public function loadNextElements($offset) {
    $this->elements = [];
    $route_names = array_keys($this->routes);

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
  public function count() {
    if (!isset($this->count)) {
      $this->count = (int) count($this->elements);
    }
    return $this->count;
  }

}
