<?php declare(strict_types=1);

/** CLI-only smoke test for the governed external mutation-tool boundary. */
if (
	PHP_SAPI !== 'cli'
	|| !isset($_SERVER['SCRIPT_FILENAME'])
	|| realpath((string)$_SERVER['SCRIPT_FILENAME']) !== __FILE__
) {
	return;
}

(static function(array $arguments): void {
	if (!interface_exists('Base3\\Api\\IBase')) {
		eval(<<<'PHP'
namespace Base3\Api;
interface IBase { public static function getName(): string; }
PHP);
	}
	if (!class_exists('Base3\\Event\\BaseEvent')) {
		eval(<<<'PHP'
namespace Base3\Event;
class BaseEvent {}
PHP);
	}

	$pluginDir = dirname(__DIR__, 3);
	$foundationDir = $arguments[1] ?? dirname($pluginDir) . '/AssistantFoundation/src';
	spl_autoload_register(static function(string $class) use ($pluginDir, $foundationDir): void {
		$prefixes = [
			'MissionBay\\' => $pluginDir . '/src/',
			'AssistantFoundation\\' => $foundationDir . '/'
		];
		foreach ($prefixes as $prefix => $directory) {
			if (!str_starts_with($class, $prefix)) {
				continue;
			}
			$file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (is_file($file)) {
				require_once $file;
			}
			return;
		}
	});

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
	$tool = new class($definition) implements \MissionBay\Api\IAgentTool, \MissionBay\Api\IAgentMutationGuardedTool {
		public int $calls = 0;
		public function __construct(private readonly array $definition) {}
		public static function getName(): string { return 'smokeguardedmutationtool'; }
		public function getToolDefinitions(): array { return [$this->definition]; }
		public function callTool(string $name, array $arguments, \AssistantFoundation\Api\IAgentContext $context): mixed {
			$this->calls++;
			return ['stored' => $arguments['value'] ?? null];
		}
		public function captureMutationCommitSnapshot(\AssistantFoundation\Dto\AgentAction $action, string $actionFingerprint, \AssistantFoundation\Api\IAgentContext $context): \AssistantFoundation\Dto\AgentMutationCommitSnapshot {
			return new \AssistantFoundation\Dto\AgentMutationCommitSnapshot(
				$action->getId(),
				$actionFingerprint,
				['owner' => 'smoke'],
				['record' => 'v1']
			);
		}
		public function getActionReview(\AssistantFoundation\Dto\AgentAction $action, \AssistantFoundation\Dto\AgentMutationCommitSnapshot $snapshot, \AssistantFoundation\Api\IAgentContext $context): \AssistantFoundation\Dto\AgentActionReview {
			return new \AssistantFoundation\Dto\AgentActionReview(
				'Confirm smoke update',
				'Update the controlled smoke value?',
				['Value' => $action->getInput()['value'] ?? '']
			);
		}
		public function validateMutationCommit(\AssistantFoundation\Dto\AgentAction $action, \AssistantFoundation\Dto\AgentMutationCommitSnapshot $snapshot, \AssistantFoundation\Api\IAgentContext $context): \AssistantFoundation\Dto\AgentMutationCommitDecision {
			return ($snapshot->getResourceVersions()['record'] ?? '') === 'v1'
				? \AssistantFoundation\Dto\AgentMutationCommitDecision::allow('Smoke snapshot is current.')
				: \AssistantFoundation\Dto\AgentMutationCommitDecision::deny(\AssistantFoundation\Dto\AgentMutationCommitDecision::CODE_STALE, 'Smoke snapshot is stale.');
		}
	};
	$capability = new \AssistantFoundation\Dto\AgentCapability(
		'smoke_update',
		'Smoke update',
		'Updates a controlled smoke value.',
		'test',
		['smoke'],
		0,
		$definition
	);
	$context = new \MissionBay\Context\AgentContext(null, [
		'runtime_id' => 'neuronai',
		'turn_id' => 'smoke-turn',
		'conversation_id' => 'smoke-conversation',
		'conversation_owner_key' => str_repeat('a', 64),
		'config_group' => 'smoke-group',
		'config_name' => 'smoke-config',
		'chatbot_key' => 'smoke-group:smoke-config',
		'prompt_text' => 'Update the smoke value.'
	]);
	$context->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::TOOL_DEFINITIONS, [$definition]);
	$context->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::TOOLS, [$tool]);
	$context->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::MUTATION_TOOL_NAMES, ['smoke_update']);
	$fingerprint = new \MissionBay\Orchestrator\AgentActionFingerprint();
	$semantics = new \MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics();
	$toolSet = new \MissionBay\Tool\Profile\MissionBayAgentToolSet(
		new \AssistantFoundation\Dto\AgentCapabilityCatalog([$capability]),
		['smoke_update' => $tool],
		[$tool],
		$context,
		new \MissionBay\Orchestrator\Service\AgentToolContractValidationService(),
		$semantics,
		$fingerprint,
		new \MissionBay\Orchestrator\Service\AgentMutationCommitGuardService($fingerprint, null, $semantics)
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
		new \AssistantFoundation\Dto\AgentInteractionResponse(
			$request->getId(),
			\AssistantFoundation\Dto\AgentInteractionResponse::DECISION_APPROVE
		),
		$metadata
	);
	if (!$result->isSuccess() || $tool->calls !== 1 || ($result->getOutput()['stored'] ?? null) !== 'Atlas') {
		throw new \RuntimeException('Guarded mutation did not execute exactly once after approval: ' . json_encode($result->toArray()));
	}

	echo "MissionBay external mutation tool set smoke test OK.\n";
})($argv);
