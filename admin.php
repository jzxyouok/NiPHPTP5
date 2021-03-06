<?php
if (version_compare(PHP_VERSION, '5.4.0', '<')) {
	die('require PHP >= 5.4.0 !');
}

define('APP_DEBUG', true);
define('BIND_MODULE', 'admin');
define('APP_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR);
define('CONF_PATH', APP_PATH . 'config' . DIRECTORY_SEPARATOR);
define('EXTEND_PATH', APP_PATH . 'extend' . DIRECTORY_SEPARATOR);
define('TEMP_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR);
ini_set('max_execution_time', 1800);
require __DIR__ . DIRECTORY_SEPARATOR . 'thinkphp' . DIRECTORY_SEPARATOR . 'start.php';
