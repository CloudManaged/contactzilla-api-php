<?php
// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

define('SERVICE_BUILDER_PATH', __DIR__.'/../services/'.APPLICATION_ENV.'.json');

$loader = require_once __DIR__.'/../vendor/autoload.php';
$loader->add('Contactzilla\\Api\\Client\\Tests', __DIR__);