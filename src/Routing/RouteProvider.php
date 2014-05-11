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

/**
 * A Route Provider front-end for all Drupal-stored routes.
 */
class RouteProvider implements RouteProviderInterface, EventSubscriberInterface 
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
   * Finds routes that may potentially match the request.
   *
   * This may return a mixed list of class instances, but all routes returned
   * must extend the core symfony route. The classes may also implement
   * RouteObjectInterface to link to a content document.
   *
   * This method may not throw an exception based on implementation specific
   * restrictions on the url. That case is considered a not found - returning
   * an empty array. Exceptions are only used to abort the whole request in
   * case something is seriously broken, like the storage backend being down.
   *
   * Note that implementations may not implement an optimal matching
   * algorithm, simply a reasonable first pass.  That allows for potentially
   * very large route sets to be filtered down to likely candidates, which
   * may then be filtered in memory more completely.
   *
   * @param Request $request A request against which to match.
   *
   * @return \Symfony\Component\Routing\RouteCollection with all urls that
   *      could potentially match $request. Empty collection if nothing can
   *      match.
   *
   * @todo Should this method's found routes also be included in the cache?
   */
  public function getRouteCollectionForRequest(Request $request) {

    // The '_system_path' has language prefix stripped and path alias resolved,
    // whereas getPathInfo() returns the requested path. In Drupal, the request
    // always contains a system_path attribute, but this component may get
    // adopted by non-Drupal projects. Some unit tests also skip initializing
    // '_system_path'.
    // @todo Consider abstracting this to a separate object.
    if ($request->attributes->has('_system_path')) {
      // _system_path never has leading or trailing slashes.
      $path = '/' . $request->attributes->get('_system_path');
    }
    else {
      // getPathInfo() always has leading slash, and might or might not have a
      // trailing slash.
      $path = rtrim($request->getPathInfo(), '/');
    }

    $collection = $this->getRoutesByPath($path);

    // Try rebuilding the router if it is necessary.
    if (!$collection->count() && $this->routeBuilder->rebuildIfNeeded()) {
      $collection = $this->getRoutesByPath($path);
    }

    return $collection;
  }

  /**
   * Find the route using the provided route name (and parameters).
   *
   * @param string $name
   *   The route name to fetch
   * @param array $parameters
   *   The parameters as they are passed to the UrlGeneratorInterface::generate
   *   call.
   *
   * @return \Symfony\Component\Routing\Route
   *   The found route.
   *
   * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
   *   Thrown if there is no route with that name in this repository.
   */
  public function getRouteByName($name, $parameters = array()) {
    $routes = $this->getRoutesByNames(array($name), $parameters);
    if (empty($routes)) {
      throw new RouteNotFoundException(sprintf('Route "%s" does not exist.', $name));
    }

    return reset($routes);
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

      //$result = $this->connection->query('SELECT name, route FROM {' . $this->connection->escapeTable($this->tableName) . '} WHERE name IN (:names)', array(':names' => $routes_to_load));
      //$routes = $result->fetchAllKeyed();
      
      //$this->redis->hmget('');

      $routes = [];
      foreach ($routes as $name => $route) {
        $this->routes[$name] = unserialize($route);
      }
    }

    return array_intersect_key($this->routes, array_flip($names));
  }

  /**
   * Returns an array of path pattern outlines that could match the path parts.
   *
   * @param array $parts
   *   The parts of the path for which we want candidates.
   *
   * @return array
   *   An array of outlines that could match the specified path parts.
   */
  public function getCandidateOutlines(array $parts) {
    $number_parts = count($parts);
    $ancestors = array();
    $length = $number_parts - 1;
    $end = (1 << $number_parts) - 1;

    // The highest possible mask is a 1 bit for every part of the path. We will
    // check every value down from there to generate a possible outline.
    if ($number_parts == 1) {
      $masks = array(1);
    }
    elseif ($number_parts <= 3) {
      // Optimization - don't query the state system for short paths. This also
      // insulates against the state entry for masks going missing for common
      // user-facing paths since we generate all values without checking state.
      $masks = range($end, 1);
    }
    elseif ($number_parts <= 0) {
      // No path can match, short-circuit the process.
      $masks = array();
    }
    else {
      // Get the actual patterns that exist out of state.
      $masks = (array) $this->state->get('routing.menu_masks.' . $this->tableName, array());
    }


    // Only examine patterns that actually exist as router items (the masks).
    foreach ($masks as $i) {
      if ($i > $end) {
        // Only look at masks that are not longer than the path of interest.
        continue;
      }
      elseif ($i < (1 << $length)) {
        // We have exhausted the masks of a given length, so decrease the length.
        --$length;
      }
      $current = '';
      for ($j = $length; $j >= 0; $j--) {
        // Check the bit on the $j offset.
        if ($i & (1 << $j)) {
          // Bit one means the original value.
          $current .= $parts[$length - $j];
        }
        else {
          // Bit zero means means wildcard.
          $current .= '%';
        }
        // Unless we are at offset 0, add a slash.
        if ($j) {
          $current .= '/';
        }
      }
      $ancestors[] = '/' . $current;
    }
    return $ancestors;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutesByPattern($pattern) {
    $path = RouteCompiler::getPatternOutline($pattern);

    return $this->getRoutesByPath($path);
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

    print_r($ancestors);
    $routes = [];
    $route_names = $this->redis->hmget('router:patterns', $ancestors);
    foreach ($route_names as $name) {
      $routes[] = $this->redis->hmget('router:'.$name,['name','route']);  
    }
    
    var_dump($routes);
    foreach ($routes as $route) {
      
      $route = unserialize($route[1]);
      if (preg_match($route->compile()->getRegex(), $path, $matches)) {
        $collection->add($name, $route);
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

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->routes  = array();
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[RoutingEvents::FINISHED][] = array('reset');
    return $events;
  }

}
