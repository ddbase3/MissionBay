<?php declare(strict_types=1);

/** Dependency-free, read-only diagnostic check smoke. */

use Base3\CoreFoundation\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\MissionBay\Diagnostics\AgentApprovalContractDiagnosticCheck;
use Base3\MissionBay\Diagnostics\Scope\AgentExecutionDiagnosticScope;

$root = dirname(__DIR__, 4);
foreach (['DIR_ROOT' => $root . '/', 'DIR_PLUGIN' => $root . '/plugin/', 'DIR_TEST' => $root . '/test/'] as $name => $value) {
	if (!defined($name)) define($name, $value);
}
require_once DIR_ROOT . 'bootstrap/Autoloader.php';
Base3\Bootstrap\Autoloader::register();

$check = new AgentApprovalContractDiagnosticCheck();
if ($check->getScope() !== AgentExecutionDiagnosticScope::getName()) {
	throw new RuntimeException('HITL contract check is outside the existing Agent Execution diagnostic scope.');
}
foreach (range(1, 3) as $iteration) {
	$result = $check->run();
	if ($result->status !== DiagnosticCheckResult::PASSED
		|| ($result->details['checked'] ?? 0) !== 8
		|| $result->remediationKey !== '') {
		throw new RuntimeException('Read-only HITL DTO contract diagnostic failed.');
	}
}
echo "HITL approval contract diagnostics: 8 checks, repeatable and read-only: OK\n";
