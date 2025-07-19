#!/usr/bin/env php
<?php declare(strict_types=1);

use Base3\Token\FileToken\FileToken;

ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Api/ICheck.php';
require_once __DIR__ . '/../../src/Token/Api/IToken.php';
require_once __DIR__ . '/../../src/Token/FileToken/FileToken.php';

define('DIR_LOCAL', __DIR__ . '/../../local/');

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

