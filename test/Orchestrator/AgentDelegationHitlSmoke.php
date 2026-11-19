<?php declare(strict_types=1);

/** Dependency-free execution of the nested A to B to C approval scenarios. */

$root = dirname(__DIR__, 4);
foreach (['DIR_ROOT' => $root . '/', 'DIR_PLUGIN' => $root . '/plugin/', 'DIR_TEST' => $root . '/test/'] as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}
require_once DIR_ROOT . 'bootstrap/Autoloader.php';
Base3\Bootstrap\Autoloader::register();

Base3\MissionBay\Test\Orchestrator\Fixture\AgentDelegationHitlScenario::run();
echo "Nested A-B-C approval and denial checks passed.\n";
