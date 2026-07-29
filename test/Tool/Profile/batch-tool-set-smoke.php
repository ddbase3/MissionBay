<?php declare(strict_types=1);

/** CLI-only smoke test for one approval and normal guarded batch child execution. */
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
	if (!interface_exists('Base3\\Event\\Api\\IEventManager')) {
		eval(<<<'PHP'
namespace Base3\Event\Api;
interface IEventManager { public function fire(object|string $event, ...$args): array; }
PHP);
	}
	if (!interface_exists('Base3\\Api\\IOutputSchemaProvider')) {
		eval(<<<'PHP'
namespace Base3\Api;
interface IOutputSchemaProvider { public function getOutputSchemas(): array; }
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

	$targetDefinition = [
		'type' => 'function',
		'label' => 'Set smoke plugin state',
		'readOnlyHint' => false,
		'mutation' => true,
		'requiresApproval' => true,
		'commitGuardRequired' => true,
		'sideEffectHint' => true,
		'batchable' => true,
		'batchIndependent' => true,
		'maxBatchSize' => 3,
		'function' => [
			'name' => 'set_smoke_plugin_state',
			'description' => 'Sets one smoke plugin state.',
			'parameters' => [
				'type' => 'object',
				'additionalProperties' => false,
				'properties' => [
					'plugin' => ['type' => 'string', 'minLength' => 1],
					'state' => ['type' => 'string', 'enum' => ['active', 'inactive']]
				],
				'required' => ['plugin', 'state']
			]
		]
	];
	$targetTool = new class($targetDefinition) implements \MissionBay\Api\IAgentTool, \MissionBay\Api\IAgentMutationGuardedTool {
		public array $calls = [];
		public function __construct(private readonly array $definition) {}
		public static function getName(): string { return 'smokebatchtargettool'; }
		public function getToolDefinitions(): array { return [$this->definition]; }
		public function callTool(string $name, array $arguments, \AssistantFoundation\Api\IAgentContext $context): mixed {
			$this->calls[] = $arguments;
			return ['ok' => true, 'plugin' => $arguments['plugin'], 'state' => $arguments['state']];
		}
		public function captureMutationCommitSnapshot(\AssistantFoundation\Dto\AgentAction $action, string $actionFingerprint, \AssistantFoundation\Api\IAgentContext $context): \AssistantFoundation\Dto\AgentMutationCommitSnapshot {
			return new \AssistantFoundation\Dto\AgentMutationCommitSnapshot(
				$action->getId(),
				$actionFingerprint,
				['owner' => 'smoke'],
				['plugin' => 'v1'],
				metadata: ['plugin' => $action->getInput()['plugin'] ?? '']
			);
		}
		public function getActionReview(\AssistantFoundation\Dto\AgentAction $action, \AssistantFoundation\Dto\AgentMutationCommitSnapshot $snapshot, \AssistantFoundation\Api\IAgentContext $context): \AssistantFoundation\Dto\AgentActionReview {
			return new \AssistantFoundation\Dto\AgentActionReview(
				'Confirm smoke plugin state',
				'Set one plugin state.',
				['Plugin' => $action->getInput()['plugin'] ?? '', 'State' => $action->getInput()['state'] ?? '']
			);
		}
		public function validateMutationCommit(\AssistantFoundation\Dto\AgentAction $action, \AssistantFoundation\Dto\AgentMutationCommitSnapshot $snapshot, \AssistantFoundation\Api\IAgentContext $context): \AssistantFoundation\Dto\AgentMutationCommitDecision {
			return ($snapshot->getResourceVersions()['plugin'] ?? '') === 'v1'
				? \AssistantFoundation\Dto\AgentMutationCommitDecision::allow('Smoke plugin snapshot is current.')
				: \AssistantFoundation\Dto\AgentMutationCommitDecision::deny(\AssistantFoundation\Dto\AgentMutationCommitDecision::CODE_STALE, 'Smoke plugin snapshot is stale.');
		}
	};

	$fingerprint = new \MissionBay\Orchestrator\AgentActionFingerprint();
	$semantics = new \MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics();
	$contractValidation = new \MissionBay\Orchestrator\Service\AgentToolContractValidationService();
	$commitGuard = new \MissionBay\Orchestrator\Service\AgentMutationCommitGuardService($fingerprint, null, $semantics);
	$batchExecution = new \MissionBay\Orchestrator\Service\AgentBatchExecutionService($commitGuard);
	$batchResults = new \MissionBay\Orchestrator\Service\AgentBatchResultService();
	$batchTool = new \MissionBay\Resource\AgentTool\Batch\BatchAgentTool(
		$semantics,
		$contractValidation,
		$fingerprint,
		$commitGuard,
		'smoke-batch'
	);
	$context = new \MissionBay\Context\AgentContext(null, [
		'runtime_id' => 'smoke-runtime',
		'conversation_id' => 'smoke-conversation'
	]);
	$configResolver = new class implements \MissionBay\Api\IAgentConfigValueResolver {
		public function resolveValue(array|string|int|float|bool|null $config): mixed { return $config; }
	};
	$eventManager = new class implements \Base3\Event\Api\IEventManager {
		public function fire(object|string $event, ...$args): array { return []; }
	};
	$batchWrapper = new \MissionBay\Resource\ConfiguredAgentToolResource($configResolver, $eventManager, 'configured-batch');
	$batchWrapper->setConfig(['namespace' => 'batch']);
	$batchWrapper->init(['tool' => [$batchTool]], $context);
	$targetWrapper = new \MissionBay\Resource\ConfiguredAgentToolResource($configResolver, $eventManager, 'configured-target');
	$targetWrapper->setConfig(['namespace' => 'plugins']);
	$targetWrapper->init(['tool' => [$targetTool]], $context);

	$batchFunction = 'batch__' . \MissionBay\Resource\AgentTool\Batch\BatchAgentTool::FN_EXECUTE;
	$targetFunction = 'plugins__set_smoke_plugin_state';
	$definitions = array_merge($batchWrapper->getToolDefinitions(), $targetWrapper->getToolDefinitions());
	$tools = [$batchWrapper, $targetWrapper];
	$context->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::TOOL_DEFINITIONS, $definitions);
	$context->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::TOOLS, $tools);
	$context->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::MUTATION_TOOL_NAMES, [
		$batchFunction,
		$targetFunction
	]);

	$catalog = (new \MissionBay\Capability\AgentCapabilityCatalogBuilder())->build($tools, $definitions);
	$toolSet = new \MissionBay\Tool\Profile\MissionBayAgentToolSet(
		$catalog,
		[
			$batchFunction => $batchWrapper,
			$targetFunction => $targetWrapper
		],
		$tools,
		$context,
		$contractValidation,
		$semantics,
		$fingerprint,
		$commitGuard,
		null,
		[],
		$batchExecution,
		$batchResults
	);

	$arguments = [
		'target_function' => $targetFunction,
		'common_arguments' => ['state' => 'active'],
		'items' => [
			['label' => 'Plugin A', 'arguments' => ['plugin' => 'plugina']],
			['label' => 'Plugin B', 'arguments' => ['plugin' => 'pluginb']]
		]
	];
	$suspension = $toolSet->prepareSuspension('batch-call', $batchFunction, $arguments);
	if ($suspension === null || $targetTool->calls !== []) {
		throw new \RuntimeException('Batch did not suspend before child execution.');
	}
	$request = $suspension->getRequests()[0];
	$reviewSummary = $request->getSummary();
	if (($reviewSummary['Number of actions'] ?? 0) !== 2) {
		throw new \RuntimeException('Batch review does not describe both child actions.');
	}
	if ($request->getTitle() !== 'Confirm 2 actions: Confirm smoke plugin state') {
		throw new \RuntimeException('Batch review does not derive its title from the child reviews.');
	}
	if (isset($reviewSummary['Target function']) || isset($reviewSummary['Items'])) {
		throw new \RuntimeException('Batch review still exposes technical or JSON-style item summary fields.');
	}
	if (
		!is_string($reviewSummary['1. Plugin A'] ?? null)
		|| !str_contains($reviewSummary['1. Plugin A'], 'Plugin: plugina')
		|| !is_string($reviewSummary['2. Plugin B'] ?? null)
		|| !str_contains($reviewSummary['2. Plugin B'], 'State: active')
	) {
		throw new \RuntimeException('Batch review does not expose directly renderable child review entries.');
	}

	$singleItemRejected = false;
	try {
		$toolSet->prepareSuspension('single-batch-call', $batchFunction, [
			'target_function' => $targetFunction,
			'items' => [[
				'label' => 'Plugin A',
				'arguments' => ['plugin' => 'plugina', 'state' => 'active']
			]]
		]);
	}
	catch (\RuntimeException $e) {
		$singleItemRejected = str_contains($e->getMessage(), 'does not satisfy the declared tool contract')
			|| str_contains($e->getMessage(), 'fewer items than allowed')
			|| str_contains($e->getMessage(), 'between 2 and');
	}
	if (!$singleItemRejected) {
		throw new \RuntimeException('Batch tool accepted a single child action instead of requiring direct tool execution.');
	}

	$internalRequestMetadata = $request->getMetadata();
	$internalRequestMetadata[\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT] =
		$suspension->getState()[\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT];
	$internalRequest = new \AssistantFoundation\Dto\AgentInteractionRequest(
		$request->getId(),
		$request->getKind(),
		$request->getAction(),
		$request->getActionFingerprint(),
		$request->getTitle(),
		$request->getMessage(),
		$request->getSummary(),
		$request->getRisk(),
		$internalRequestMetadata
	);
	$internalSuspension = new \AssistantFoundation\Dto\AgentSuspension(
		'internal-batch-suspension',
		\AssistantFoundation\Dto\AgentExecutionStatus::AWAITING_APPROVAL,
		[$internalRequest],
		[],
		gmdate('c')
	);
	$resumeRepository = new class implements \AssistantFoundation\Api\IAgentSuspensionRepository {
		public function create(\AssistantFoundation\Dto\AgentSuspension $suspension, int $ttlSeconds): string { return 'resume-handle'; }
		public function claim(string $resumeHandle): \AssistantFoundation\Dto\AgentSuspensionClaim { throw new \RuntimeException('Not used.'); }
		public function release(\AssistantFoundation\Dto\AgentSuspensionClaim $claim): void {}
		public function consume(\AssistantFoundation\Dto\AgentSuspensionClaim $claim): void {}
	};
	$resumeContext = new \MissionBay\Context\AgentContext();
	$resumeContext->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::TOOL_DEFINITIONS, $definitions);
	$resumeContext->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::TOOLS, $tools);
	$resumeContext->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::PENDING_TOOL_CALLS, []);
	$resumeContext->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::TOOL_RESULTS, []);
	$resumeContext->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::PREAPPROVED_ACTIONS, []);
	$resumeContext->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::SELECTED_TOOL_NAMES, [$batchFunction]);
	$resumeContext->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::ACTIONS, [$request->getAction()]);
	$resumeContext->setVar(\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::MODEL_RESULTS, []);
	$resume = new \AssistantFoundation\Dto\AgentResume(
		'resume-handle',
		[new \AssistantFoundation\Dto\AgentInteractionResponse(
			$request->getId(),
			\AssistantFoundation\Dto\AgentInteractionResponse::DECISION_APPROVE
		)]
	);
	$claim = new \AssistantFoundation\Dto\AgentSuspensionClaim('resume-handle', 'claim-token', $internalSuspension);
	$resumeContext->setVar(
		\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::RESUME,
		new \MissionBay\Dto\Assistant\PreparedAgentResume($resume, $claim)
	);
	$resumePatch = (new \MissionBay\Orchestrator\Service\AgentActionResumeService(
		$fingerprint,
		$resumeRepository,
		null,
		null,
		$batchExecution
	))->resume($resumeContext)->getPatch();
	$resumedCalls = $resumePatch[\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::PENDING_TOOL_CALLS] ?? [];
	$resumedActions = $resumePatch[\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::ACTIONS] ?? null;
	if (count($resumedCalls) !== 2 || $resumedActions !== []) {
		$actionIds = is_array($resumedActions) ? array_map(
			static fn(mixed $action): string => $action instanceof \AssistantFoundation\Dto\AgentAction ? $action->getId() : get_debug_type($action),
			$resumedActions
		) : [get_debug_type($resumedActions)];
		throw new \RuntimeException(
			'Internal batch resume did not replace the parent envelope with normal child calls: calls='
			. count($resumedCalls) . ', actions=' . json_encode($actionIds)
			. ', patch=' . json_encode($resumePatch)
		);
	}

	$result = $toolSet->resumeSuspension(
		$suspension,
		new \AssistantFoundation\Dto\AgentInteractionResponse(
			$request->getId(),
			\AssistantFoundation\Dto\AgentInteractionResponse::DECISION_APPROVE
		)
	);
	$output = $result->getOutput();
	$aggregated = $batchResults->aggregate([
		\AssistantFoundation\Dto\AgentToolResult::success(
			'batch-call.batch.001',
			'set_smoke_plugin_state',
			['plugin' => 'plugina', 'state' => 'active'],
			['ok' => true],
			['tool_call' => ['agent_batch' => [
				'parent_call' => ['id' => 'batch-call', 'name' => $batchFunction, 'arguments' => $arguments],
				'index' => 1,
				'size' => 2,
				'label' => 'Plugin A'
			]]]
		),
		\AssistantFoundation\Dto\AgentToolResult::failure(
			'batch-call.batch.002',
			'set_smoke_plugin_state',
			['plugin' => 'pluginb', 'state' => 'active'],
			'mutation_stale',
			'Smoke child became stale.',
			['tool_call' => ['agent_batch' => [
				'parent_call' => ['id' => 'batch-call', 'name' => $batchFunction, 'arguments' => $arguments],
				'index' => 2,
				'size' => 2,
				'label' => 'Plugin B'
			]]]
		)
	]);
	if (count($aggregated) !== 1 || ($aggregated[0]->getOutput()['status'] ?? '') !== 'partial') {
		throw new \RuntimeException('Batch child observations were not aggregated to one parent result.');
	}
	$reportedFailure = $batchResults->aggregateForParent(
		new \AssistantFoundation\Dto\AiToolCall('reported-failure', $batchFunction, $arguments),
		[\AssistantFoundation\Dto\AgentToolResult::success(
			'reported-failure.batch.001',
			'set_smoke_plugin_state',
			['plugin' => 'plugina', 'state' => 'active'],
			['ok' => false, 'error' => ['message' => 'Provider rejected the mutation.']],
			['tool_call' => ['agent_batch' => ['index' => 1, 'size' => 1, 'label' => 'Plugin A']]]
		)]
	);
	if (($reportedFailure->getOutput()['status'] ?? '') !== 'failed') {
		throw new \RuntimeException('Batch summary ignored an explicit ok=false child result.');
	}
	if (
		!$result->isSuccess()
		|| count($targetTool->calls) !== 2
		|| !is_array($output)
		|| ($output['status'] ?? '') !== 'success'
		|| ($output['succeeded'] ?? 0) !== 2
	) {
		throw new \RuntimeException('Approved batch did not execute and aggregate exactly two guarded child actions: ' . json_encode($result->toArray()));
	}

	echo "MissionBay batch tool set smoke test OK.\n";
})($argv);
