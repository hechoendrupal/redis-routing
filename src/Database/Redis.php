<?php
/**
 * File content
 * Drupal\routdis\Database\Redis
 */
namespace Drupal\routdis\Database;

use Predis\Client;

class Redis
{
	/**
	 * @var Predis\Client
	 */
	private $redis;
	
	/**
	 * contructor
	 * @param Array $connection default settings to create Redis connection
	 */
	public function __construct($connection)
	{
		$this->redis = new Client($connection);
	}

	/**
	 * Return Redis connection
	 * @return [type] [description]
	 */
	public function getConnection()
	{
		return $this->redis;
	}

}