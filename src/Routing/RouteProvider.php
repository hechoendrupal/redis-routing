<?php

/**
 * @file
 * Contains Drupal\routdis\Routing\RouteProvider.
 */
namespace Drupal\routdis\Routing;

use Drupal\Component\Utility\String;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Drupal\routdis\Database\Redis;
use Drupal\Core\Routing\RouteProvider as BaseRouteProvider;

/**
 * A Route Provider front-end for all Drupal-stored routes.
 */
class RouteProvider extends BaseRouteProvider implements RouteProviderInterface, EventSubscriberInterface 
{

  
  protected $redis;

  /**
   * The name of the SQL table from which to read the routes.
   *
   * @var string
   */
  protected $tableName;

  /**
   * The route builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * A cache of already-loaded routes, keyed by route name.
   *
   * @var array
   */
  protected $routes = array();

  /**
   * Constructs a new PathMatcher.
   *
   * @param \Drupal\routdis\Database\Redis $redis
   *   A redis connection object.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route builder.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param string $table
   *   The table in the database to use for matching.
   */
  public function __construct(Redis $redis, RouteBuilderInterface $route_builder, StateInterface $state, $table = 'router') {
    $this->redis = $redis->getConnection();
    $this->routeBuilder = $route_builder;
    $this->state = $state;
    $this->tableName = $table;
  }

  /**
   * Find many routes by their names using the provided list of names.
   *
   * Note that this method may not throw an exception if some of the routes
   * are not found. It will just return the list of those routes it found.
   *
   * This method exists in order to allow performance optimizations. The
   * simple implementation could be to just repeatedly call
   * $this->getRouteByName().
   *
   * @param array $names
   *   The list of names to retrieve.
   * @param array $parameters
   *   The parameters as they are passed to the UrlGeneratorInterface::generate
   *   call. (Only one array, not one for each entry in $names).
   *
   * @return \Symfony\Component\Routing\Route[]
   *   Iterable thing with the keys the names of the $names argument.
   */
  public function getRoutesByNames($names, $parameters = array()) {

    if (empty($names)) {
      throw new \InvalidArgumentException('You must specify the route names to load');
    }

    $routes_to_load = array_diff($names, array_keys($this->routes));

    if ($routes_to_load) {
      foreach ($routes_to_load as $name) {
        $routes[] = $this->redis->hmget('router:'.$name,['name','route']);
      }
      
      foreach ($routes as $route) {
        $name = $route[0];
        $this->routes[$name] = unserialize($route[1]);
      }
    }

    return array_intersect_key($this->routes, array_flip($names));
  }

  /**
   * Get all routes which match a certain pattern.
   *
   * @param string $path
   *   The route pattern to search for (contains % as placeholders).
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   Returns a route collection of matching routes.
   */
  protected function getRoutesByPath($path) {
    // Filter out each empty value, though allow '0' and 0, which would be
    // filtered out by empty().
    $parts = array_values(array_filter(explode('/', $path), function($value) {
      return $value !== NULL && $value !== '';
    }));

    $collection = new RouteCollection();

    $ancestors = $this->getCandidateOutlines($parts);
    if (empty($ancestors)) {
      return $collection;
    }

    $routes = [];
    $route_names = $this->redis->hmget('router:patterns', $ancestors);
    foreach ($route_names as $name) {
      $routes[] = $this->redis->hmget('router:'.$name,['name','route','fit']);
    }
        
    foreach ($routes as $route) {
      if (!empty($route[1])){
        $name = $route[0];
        $route = unserialize($route[1]);
        if (preg_match($route->compile()->getRegex(), $path, $matches)) {
          $collection->add($name, $route);
        }
      }
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllRoutes() {
    return new LazyLoadingRouteCollection($this->connection, $this->tableName);
  }

}
