Redis for Routing
==========

This project replace the routing db storage to redis storage.

This module use a [Predis](ttps://github.com/nrk/predis) librarie to connect on Redis service, 
in Drupal 8 the composer.json is versioned in core what is a problem because this module need 
load the predis librarie before to load Drupal bootstrap.


### Instalation
```bash 
$ cd /path/to/drupal/8
$ composer require "predis/predis":"dev-master"
$ cd modules
$ git clone git@github.com:dmouse/redis-menu.git routdis
$ drush en -y routdis
``` 

### Minimal requirements
 * composer
 * drush
 * redis
 * git

