<?php declare(strict_types=1);

/** CLI-only smoke test for the governed external mutation-tool boundary. */
if (
	PHP_SAPI !== 'cli'
	|| !isset($_SERVER['SCRIPT_FILENAME'])
	|| realpath((string)$_SERVER['SCRIPT_FILENAME']) !== __FILE__
) {
	return;
}

(static function(): void {
	$root = dirname(__DIR__, 5) . '/';
	define('DIR_ROOT', $root);
	define('DIR_PLUGIN', $root . 'plugin/');
	define('DIR_TEST', $root . 'test/');
	require_once $root . 'bootstrap/Autoloader.php';
	\Base3\Bootstrap\Autoloader::register();

	require_once __DIR__ . '/MutationToolSetSmokeDouble1.php';

	$definition = [
		'type' => 'function',
		'readOnlyHint' => false,
		'mutation' => true,
		'requiresApproval' => true,
		'commitGuardRequired' => true,
		'sideEffectHint' => true,
		'function' => [
			'name' => 'smoke_update',
			'description' => 'Updates a controlled smoke value.',
			'parameters' => [
				'type' => 'object',
				'properties' => ['value' => ['type' => 'string']],
				'required' => ['value']
			]
		]
	];
	$tool = new \Base3\MissionBay\Test\Tool\Profile\MutationToolSetSmokeDouble1($definition);
	$capability = new \Base3\AssistantFoundation\Dto\AgentCapability(
		'smoke_update',
		'Smoke update',
		'Updates a controlled smoke value.',
		'test',
		['smoke'],
		0,
		$definition
	);
	$context = new \Base3\MissionBay\Context\AgentContext(null, [
		'runtime_id' => 'neuronai',
		'turn_id' => 'smoke-turn',
		'conversation_id' => 'smoke-conversation',
		'conversation_channel_id' => 'smoke-chatbot',
		'config_group' => 'smoke-group',
		'config_name' => 'smoke-config',
		'prompt_text' => 'Update the smoke value.'
	]);
	$context->setVar(\Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::TOOL_DEFINITIONS, [$definition]);
	$context->setVar(\Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::TOOLS, [$tool]);
	$context->setVar(\Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::MUTATION_TOOL_NAMES, ['smoke_update']);
	$fingerprint = new \Base3\MissionBay\Orchestrator\AgentActionFingerprint();
	$semantics = new \Base3\MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics();
	$toolSet = new \Base3\MissionBay\Tool\Profile\MissionBayAgentToolSet(
		new \Base3\AssistantFoundation\Dto\AgentCapabilityCatalog([$capability]),
		['smoke_update' => $tool],
		[$tool],
		$context,
		new \Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService(),
		$semantics,
		$fingerprint,
		new \Base3\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService($fingerprint, null, $semantics)
	);

	$metadata = [
		'iteration' => 1,
		'call_index' => 1,
		'label' => 'Smoke update',
		'trace' => ['prompt_text' => 'Update the smoke value.']
	];
	$suspension = $toolSet->prepareSuspension('smoke-call', 'smoke_update', ['value' => 'Atlas'], $metadata);
	if ($suspension === null || $tool->calls !== 0) {
		throw new \RuntimeException('Guarded mutation did not suspend before execution.');
	}
	$request = $suspension->getRequests()[0];
	$result = $toolSet->resumeSuspension(
		$suspension,
		new \Base3\AssistantFoundation\Dto\AgentInteractionResponse(
			$request->getId(),
			\Base3\AssistantFoundation\Dto\AgentInteractionResponse::DECISION_APPROVE
		),
		$metadata
	);
	if (!$result->isSuccess() || $tool->calls !== 1 || ($result->getOutput()['stored'] ?? null) !== 'Atlas') {
		throw new \RuntimeException('Guarded mutation did not execute exactly once after approval: ' . json_encode($result->toArray()));
	}

	echo "MissionBay external mutation tool set smoke test OK.\n";
})();

