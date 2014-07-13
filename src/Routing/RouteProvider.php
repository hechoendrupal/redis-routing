<?php
/**
 * @file
 * Contains Drupal\redis_routing\Routing\RouteProvider.
 */
namespace Drupal\redis_routing\Routing;

use Drupal\Core\State\StateInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Routing\RouteProvider as BaseRouteProvider;
use Drupal\predis\RedisConnectionInterface as Redis;

/**
 * A Route Provider front-end for all Drupal-stored routes.
 */
class RouteProvider extends BaseRouteProvider implements RouteProviderInterface, EventSubscriberInterface
{

  /**
   * @var \Drupal\predis\RedisConnectionInterface
   */
  protected $redis;

  /**
   * The prefix name for routes in Redis.
   *
   * @var string
   */
  protected $prefix;

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
   * @param \Drupal\predis\RedisConnectionInterface $redis
   *   A redis connection object.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route builder.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param string $prefix
   */
  public function __construct(Redis $redis, RouteBuilderInterface $route_builder, StateInterface $state, $prefix = 'router')
  {
    $this->redis = $redis->getConnection();
    $this->routeBuilder = $route_builder;
    $this->state = $state;
    $this->prefix = $prefix;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutesByNames($names, $parameters = array())
  {
    if (empty($names)) {
      throw new \InvalidArgumentException('You must specify the route names to load');
    }

    $routes_to_load = array_diff($names, array_keys($this->routes));

    if ($routes_to_load) {
      $routes = [];
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
   * {@inheritdoc}
   */
  protected function getRoutesByPath($path)
  {
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
    $route_names = $this->redis->hmget($this->prefix.':patterns', $ancestors);
    foreach ($route_names as $name) {
      $routes[] = $this->redis->hmget($this->prefix.':'.$name,['name','route','fit']);
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
  public function getAllRoutes()
  {
    return new LazyLoadingRouteCollection($this->redis, $this->prefix);
  }
}
