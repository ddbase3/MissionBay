#!/usr/bin/env php
<?php declare(strict_types=1);

use Base3\Core\Token\FileToken\FileToken;

ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

$root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
define('DIR_ROOT', $root);
define('DIR_PLUGIN', $root . 'plugin' . DIRECTORY_SEPARATOR);
require_once $root . 'bootstrap/Autoloader.php';
\Base3\Bootstrap\Autoloader::register();

define('DIR_LOCAL', $root . 'local' . DIRECTORY_SEPARATOR);

// default values
$scope = 'api';
$id = 'missionbaymcpserver';
$duration = 365 * 24 * 3600; // 1 year

// arguments: php generate-token.php [--id=id] [--duration=seconds]
foreach ($argv as $arg) {
	if (str_starts_with($arg, '--id=')) {
		$id = substr($arg, 5);
	} elseif (str_starts_with($arg, '--duration=')) {
		$duration = (int)substr($arg, 11);
	}
}

$tokenService = new FileToken();
$token = $tokenService->create($scope, $id, 32, $duration);

echo "Token generated for scope '$scope' and id '$id':\n";
echo $token . "\n";

echo $tokenService->check('api', 'missionbaymcpserver', $token) ? "OK\n" : "Invalid\n";

