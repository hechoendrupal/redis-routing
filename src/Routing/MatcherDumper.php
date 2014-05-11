<?php

/**
 * @file
 * Contains Drupal\routdis\Routing\MatcherDumper
 */
namespace Drupal\routdis\Routing;

use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Routing\MatcherDumperInterface;
use Drupal\Core\State\State;
use Drupal\Core\Routing\MatcherDumper as BaseMatcherDumper;
use Drupal\routdis\Database\Redis;

class MatcherDumper extends BaseMatcherDumper implements MatcherDumperInterface
{

	protected $redis;

	protected $state;

	protected $tableName;

	public function __construct(Redis $redis, State $state, $table = 'router')
	{	
		$this->state = $state;
		$this->redis = $redis->getConnection();
		$this->tableName = $table;
	}

	public function dump(array $options = array())
	{
		$masks = array_flip($this->state->get('routing.menu_masks.'.$this->tableName, []));
		try {
			
      // Get routes in chunks
      $this->redis->del('router:patterns');
			$route_chunks = array_chunk($this->routes->all(), 500, TRUE);
			foreach ($route_chunks as $routes) {
				$name = [];
				foreach ($routes as $name => $route) {
					$route->setOption('compiler_class', '\Drupal\Core\Routing\RouteCompiler');
					$compiled = $route->compile();
					$masks[$compiled->getFit()] = 1;

					$this->redis->del('router:'.$name);
	        $this->redis->hset('router:'.$name, "name", $name);
	        $this->redis->hset('router:'.$name, "fit", $compiled->getFit());
	        $this->redis->hset('router:'.$name, "path", $compiled->getPath());
	        $this->redis->hset('router:'.$name, "pattern_outline", $compiled->getPatternOutline());
	        $this->redis->hset('router:'.$name, "number_parts", $compiled->getNumParts());
	        $this->redis->hset('router:'.$name, "route", serialize($route) );
	        $this->redis->hset('router:patterns', $compiled->getPatternOutline(), $name);
				}
			}
		} catch (\Exception $e) {
      watchdog_exception('Routing', $e);
      throw $e;
    }

    $masks = array_keys($masks);
    rsort($masks);
    $this->state->set('routing.menu_masks.' . $this->tableName, $masks);
    $this->routes = NULL;
	}
	
}