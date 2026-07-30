<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Resource\AgentTool\Batch;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionReview;
use AssistantFoundation\Dto\AgentInstructionBlock;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Api\IAgentBatchTool;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Resource\AbstractAgentResource;

/**
 * Generic approval envelope for independent repetitions of one guarded tool
 * function. Target mutations remain implemented and executed by their normal
 * single-item tools.
 */
final class BatchAgentTool extends AbstractAgentResource implements IAgentBatchTool, IAgentContextContributor {

	public const FN_EXECUTE = 'execute_agent_tool_batch';

	private const DEFAULT_MAX_BATCH_SIZE = 25;
	private const ABSOLUTE_MAX_BATCH_SIZE = 100;
	private const CONTEXT_PRIORITY = 30;
	private const SNAPSHOT_CHILDREN = 'children';

	public function __construct(
		private readonly AgentToolDefinitionSemantics $definitionSemantics,
		private readonly AgentToolContractValidationService $contractValidationService,
		private readonly AgentActionFingerprint $fingerprint,
		private readonly AgentMutationCommitGuardService $mutationCommitGuardService,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'batchagenttool';
	}

	public function getDescription(): string {
		return 'Use this generic tool whenever the user requests the same write or action operation for two or more independent targets and the target function declares batchable=true and batchIndependent=true. It replaces repeated individual calls with one combined approval while preserving every child action\'s normal commit guard and single-tool execution path. For exactly one action, call the target function directly.';
	}

	public function contribute(IAgentContext $context): iterable {
		return [new AgentInstructionBlock(
			id: 'batch-tool-usage',
			content: implode("\n", [
				'Batch tool usage:',
				'- When the user requests the same write or action operation for two or more independent targets, prefer execute_agent_tool_batch if the target function declares batchable=true and batchIndependent=true.',
				'- Use the target function directly for exactly one action.',
				'- Do not batch different target functions or actions that depend on results from previous items.',
				'- The batch tool asks for one combined approval; every child action still uses its normal individual commit guard and single-tool execution path.'
			]),
			priority: self::CONTEXT_PRIORITY,
			source: $this->id(),
			metadata: ['implementation' => static::getName()]
		)];
	}

	public function getPriority(): int {
		return self::CONTEXT_PRIORITY;
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Execute Agent Tool Batch',
			'category' => 'agent_control',
			'tags' => ['agent', 'tool', 'batch', 'bulk', 'multiple', 'mass-action', 'multi-item', 'mutation', 'repeat'],
			'priority' => 95,
			'alwaysAvailable' => true,
			'readOnlyHint' => false,
			'mutation' => true,
			'requiresApproval' => true,
			'commitGuardRequired' => true,
			'sideEffectHint' => true,
			'batchable' => false,
			'batchIndependent' => false,
			'function' => [
				'name' => self::FN_EXECUTE,
				'description' => 'Use this tool instead of repeated individual calls whenever the user requests the same write or action operation for at least two independent targets and the target function declares batchable=true and batchIndependent=true. For exactly one action, call the target function directly. Do not use this tool for mixed target functions, dependent steps, or read-only calls. Use target_function exactly as exposed by another tool. common_arguments are merged into every item and each item.arguments may override them. The complete frozen item list is shown in one combined approval request; every child mutation is then validated and executed separately through its normal commit guard and single-tool execution path.',
				'parameters' => [
					'type' => 'object',
					'additionalProperties' => false,
					'properties' => [
						'target_function' => [
							'type' => 'string',
							'minLength' => 1,
							'description' => 'Exact function name of the batch-enabled guarded mutation to repeat.'
						],
						'common_arguments' => [
							'type' => 'object',
							'description' => 'Arguments copied into every child call before item-specific arguments are applied.'
						],
						'items' => [
							'type' => 'array',
							'minItems' => 2,
							'maxItems' => self::ABSOLUTE_MAX_BATCH_SIZE,
							'description' => 'Independent child calls. Every arguments object becomes one normal target-tool action.',
							'items' => [
								'type' => 'object',
								'additionalProperties' => false,
								'properties' => [
									'label' => [
										'type' => 'string',
										'description' => 'Optional short human-readable item label.'
									],
									'arguments' => [
										'type' => 'object',
										'description' => 'Arguments specific to this child call.'
									]
								],
								'required' => ['arguments']
							]
						]
					],
					'required' => ['target_function', 'items']
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		throw new \RuntimeException(
			'The generic batch tool is an approval envelope and must be expanded by the governed MissionBay action pipeline.'
		);
	}

	public function isBatchFunction(string $name): bool {
		return trim($name) === self::FN_EXECUTE;
	}

	public function captureMutationCommitSnapshot(
		AgentAction $action,
		string $actionFingerprint,
		IAgentContext $context
	): AgentMutationCommitSnapshot {
		$plan = $this->resolvePlan($action, $context);
		$children = [];

		foreach ($plan['items'] as $index => $item) {
			$childCall = new AiToolCall(
				$this->childCallId($action->getId(), $index),
				$plan['target_function'],
				$item['arguments'],
				[
					'iteration' => (int)($action->getMetadata()['iteration'] ?? 0),
					'batch_capture' => true
				]
			);
			$childAction = $this->createAction($childCall);
			$childFingerprint = $this->fingerprint->create($childAction);
			$childSnapshot = $this->mutationCommitGuardService->capture($childAction, $childCall, $context);
			if (!$childSnapshot instanceof AgentMutationCommitSnapshot) {
				throw new \RuntimeException('Batch target did not provide the required child mutation commit snapshot.');
			}
			$childReview = $this->mutationCommitGuardService->getActionReview(
				$childAction,
				$childCall,
				$childSnapshot,
				$context
			);

			$children[] = [
				'index' => $index + 1,
				'label' => $item['label'],
				'call' => $childCall->toArray(),
				'action' => $childAction->toArray(),
				'fingerprint' => $childFingerprint,
				'snapshot' => $childSnapshot->toArray(),
				'review' => $childReview->toArray()
			];
		}

		return new AgentMutationCommitSnapshot(
			$action->getId(),
			$actionFingerprint,
			['resource_id' => $this->id()],
			['plan' => $this->hashData($action->getInput())],
			metadata: [
				'target_function' => $plan['target_function'],
				'item_count' => count($children),
				'max_batch_size' => $plan['max_batch_size'],
				self::SNAPSHOT_CHILDREN => $children
			]
		);
	}

	public function getActionReview(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentActionReview {
		$this->assertSnapshotMatchesAction($action, $snapshot);
		$metadata = $snapshot->getMetadata();
		$children = is_array($metadata[self::SNAPSHOT_CHILDREN] ?? null)
			? $metadata[self::SNAPSHOT_CHILDREN]
			: [];
		if ($children === []) {
			throw new \RuntimeException('Batch mutation snapshot contains no child actions.');
		}

		$reviews = [];
		$summary = [
			'Number of actions' => count($children),
			'Execution' => 'Sequential; partial success is reported per item'
		];
		foreach ($children as $child) {
			if (!is_array($child)) {
				continue;
			}
			$review = AgentActionReview::fromArray(is_array($child['review'] ?? null) ? $child['review'] : []);
			$reviews[] = $review;
			$index = (int)($child['index'] ?? count($reviews));
			$label = trim((string)($child['label'] ?? ''));
			$summary[$this->buildItemReviewLabel($index, $label, $review)] = $this->buildItemReviewValue($review);
		}

		return new AgentActionReview(
			$this->buildBatchReviewTitle($reviews),
			'Approve all listed actions once. Each action will still be validated and executed separately through its normal commit guard and single-tool execution path.',
			$summary
		);
	}

	public function validateMutationCommit(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentMutationCommitDecision {
		try {
			$this->assertSnapshotMatchesAction($action, $snapshot);
			$authorization = $snapshot->getAuthorization();
			if (trim((string)($authorization['resource_id'] ?? '')) !== $this->id()) {
				return AgentMutationCommitDecision::deny(
					AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
					'Batch mutation snapshot belongs to a different component preset.'
				);
			}
			$versions = $snapshot->getResourceVersions();
			$currentPlanHash = $this->hashData($action->getInput());
			if (!hash_equals((string)($versions['plan'] ?? ''), $currentPlanHash)) {
				return AgentMutationCommitDecision::deny(
					AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
					'Batch mutation plan no longer matches the approved action.'
				);
			}

			$plan = $this->resolvePlan($action, $context);
			$metadata = $snapshot->getMetadata();
			$children = is_array($metadata[self::SNAPSHOT_CHILDREN] ?? null)
				? $metadata[self::SNAPSHOT_CHILDREN]
				: [];
			if (
				trim((string)($metadata['target_function'] ?? '')) !== $plan['target_function']
				|| (int)($metadata['item_count'] ?? 0) !== count($plan['items'])
				|| count($children) !== count($plan['items'])
			) {
				return AgentMutationCommitDecision::deny(
					AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
					'Batch mutation snapshot does not match the approved child action list.'
				);
			}

			foreach ($children as $index => $child) {
				if (!is_array($child)) {
					return AgentMutationCommitDecision::deny(
						AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
						'Batch mutation snapshot contains an invalid child action.'
					);
				}
				$childAction = AgentAction::fromArray(is_array($child['action'] ?? null) ? $child['action'] : []);
				$childCall = AiToolCall::fromArray(is_array($child['call'] ?? null) ? $child['call'] : []);
				$childFingerprint = trim((string)($child['fingerprint'] ?? ''));
				if (
					$childAction->getId() !== $childCall->getId()
					|| $childAction->getName() !== $plan['target_function']
					|| $childAction->getInput() !== $plan['items'][$index]['arguments']
					|| !hash_equals($childFingerprint, $this->fingerprint->create($childAction))
				) {
					return AgentMutationCommitDecision::deny(
						AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
						'Batch mutation child action no longer matches the approved plan.'
					);
				}
			}
		}
		catch (\Throwable $e) {
			return AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_REJECTED,
				'Batch mutation is no longer valid: ' . $e->getMessage()
			);
		}

		return AgentMutationCommitDecision::allow(
			'Batch envelope matches the approved action. Child commit guards remain authoritative.'
		);
	}

	public function expandApprovedBatch(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context,
		string $interactionRequestId = ''
	): array {
		$this->assertSnapshotMatchesAction($action, $snapshot);
		$metadata = $snapshot->getMetadata();
		$children = is_array($metadata[self::SNAPSHOT_CHILDREN] ?? null)
			? $metadata[self::SNAPSHOT_CHILDREN]
			: [];
		$result = [];

		foreach ($children as $child) {
			if (!is_array($child)) {
				throw new \RuntimeException('Batch snapshot contains an invalid child call.');
			}
			$call = AiToolCall::fromArray(is_array($child['call'] ?? null) ? $child['call'] : []);
			$childAction = AgentAction::fromArray(is_array($child['action'] ?? null) ? $child['action'] : []);
			$childSnapshot = is_array($child['snapshot'] ?? null) ? $child['snapshot'] : null;
			if (!is_array($childSnapshot)) {
				throw new \RuntimeException('Batch child mutation snapshot is missing.');
			}

			$callMetadata = $call->getMetadata();
			$callMetadata[AgentMutationCommitGuardService::TOOL_CALL_METADATA_APPROVAL_FINGERPRINT] = $this->fingerprint->create($childAction);
			$callMetadata[AgentMutationCommitGuardService::TOOL_CALL_METADATA_INTERACTION_REQUEST] = $interactionRequestId;
			$callMetadata[AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT] = $childSnapshot;
			$callMetadata['agent_batch'] = [
				'parent_call' => [
					'id' => $action->getId(),
					'name' => $action->getName(),
					'arguments' => $action->getInput()
				],
				'target_function' => trim((string)($metadata['target_function'] ?? '')),
				'index' => (int)($child['index'] ?? count($result) + 1),
				'size' => count($children),
				'label' => trim((string)($child['label'] ?? ''))
			];
			$result[] = new AiToolCall(
				$call->getId(),
				$call->getName(),
				$call->getArguments(),
				$callMetadata
			);
		}

		return $result;
	}

	/**
	 * @return array{target_function:string,max_batch_size:int,items:array<int,array{label:string,arguments:array<string,mixed>}>}
	 */
	private function resolvePlan(AgentAction $action, IAgentContext $context): array {
		if (!$this->isBatchFunction($action->getName())) {
			throw new \InvalidArgumentException('Unsupported batch function: ' . $action->getName());
		}
		$input = $action->getInput();
		$targetFunction = trim((string)($input['target_function'] ?? ''));
		if ($targetFunction === '' || $this->isBatchFunction($targetFunction)) {
			throw new \InvalidArgumentException('Batch target_function must identify another tool function.');
		}
		$definition = $this->findDefinition($targetFunction, $context);
		if (!is_array($definition)) {
			throw new \InvalidArgumentException('Batch target function is not available in the current tool profile: ' . $targetFunction);
		}
		if (
			!$this->definitionSemantics->isMutationDefinition($definition)
			|| !$this->definitionSemantics->requiresApprovalDefinition($definition)
			|| !$this->definitionSemantics->isCommitGuardRequired($definition)
			|| !$this->definitionSemantics->isBatchableDefinition($definition)
			|| !$this->definitionSemantics->isBatchIndependentDefinition($definition)
		) {
			throw new \InvalidArgumentException(
				'Batch target must be an explicitly batchable, independent, approval-bound mutation with a required commit guard.'
			);
		}
		$targetTool = $this->findTool($targetFunction, $context);
		if (!$targetTool instanceof IAgentMutationGuardedTool) {
			throw new \InvalidArgumentException('Batch target tool does not provide the required mutation commit guard.');
		}

		$maxBatchSize = min(
			self::ABSOLUTE_MAX_BATCH_SIZE,
			$this->definitionSemantics->getMaxBatchSize($definition, self::DEFAULT_MAX_BATCH_SIZE)
		);
		$commonArguments = is_array($input['common_arguments'] ?? null) ? $input['common_arguments'] : [];
		if ($maxBatchSize < 2) {
			throw new \InvalidArgumentException(
				'Batch target function must allow at least two items: ' . $targetFunction . '.'
			);
		}
		$rawItems = is_array($input['items'] ?? null) ? $input['items'] : [];
		if (count($rawItems) < 2 || count($rawItems) > $maxBatchSize) {
			throw new \InvalidArgumentException(
				'Batch item count must be between 2 and ' . $maxBatchSize . ' for target function ' . $targetFunction . '.'
			);
		}

		$tools = $context->getVar(AgentToolLoopContextKeys::TOOLS);
		$tools = is_array($tools) ? $tools : [];
		$items = [];
		$knownInputs = [];
		foreach ($rawItems as $index => $rawItem) {
			if (!is_array($rawItem) || !is_array($rawItem['arguments'] ?? null)) {
				throw new \InvalidArgumentException('Batch item ' . ($index + 1) . ' must contain an arguments object.');
			}
			$arguments = array_replace($commonArguments, $rawItem['arguments']);
			$call = new AiToolCall($this->childCallId($action->getId(), $index), $targetFunction, $arguments);
			$validation = $this->contractValidationService->validateInput($call, $tools);
			if (!$validation->passes()) {
				throw new \InvalidArgumentException(
					'Batch item ' . ($index + 1) . ' is invalid: ' . $validation->getSummary()
				);
			}
			$inputHash = $this->hashData($arguments);
			if (isset($knownInputs[$inputHash])) {
				throw new \InvalidArgumentException(
					'Batch contains duplicate child arguments at items ' . $knownInputs[$inputHash] . ' and ' . ($index + 1) . '.'
				);
			}
			$knownInputs[$inputHash] = $index + 1;
			$items[] = [
				'label' => trim((string)($rawItem['label'] ?? '')),
				'arguments' => $arguments
			];
		}

		return [
			'target_function' => $targetFunction,
			'max_batch_size' => $maxBatchSize,
			'items' => $items
		];
	}

	/** @param array<int,AgentActionReview> $reviews */
	private function buildBatchReviewTitle(array $reviews): string {
		$titles = [];
		foreach ($reviews as $review) {
			$title = trim($review->getTitle());
			if ($title !== '') {
				$titles[$title] = true;
			}
		}
		$count = count($reviews);
		if (count($titles) === 1) {
			return 'Confirm ' . $count . ' actions: ' . array_key_first($titles);
		}
		return 'Confirm ' . $count . ' actions';
	}

	private function buildItemReviewLabel(int $index, string $label, AgentActionReview $review): string {
		$label = trim($label);
		if ($label === '') {
			$label = trim($review->getTitle());
		}
		return $index . '. ' . ($label !== '' ? $label : 'Action');
	}

	private function buildItemReviewValue(AgentActionReview $review): string {
		$parts = [];
		$message = trim($review->getMessage());
		if ($message !== '') {
			$parts[] = $message;
		}
		foreach ($review->getSummary() as $label => $value) {
			$parts[] = trim((string)$label) . ': ' . $this->formatReviewValue($value);
		}
		return implode(' · ', array_filter($parts, static fn(string $part): bool => trim($part) !== ''));
	}

	private function formatReviewValue(mixed $value): string {
		if ($value === null || $value === '') {
			return '-';
		}
		if (is_bool($value)) {
			return $value ? 'Yes' : 'No';
		}
		if (is_scalar($value)) {
			return trim((string)$value);
		}
		$json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return is_string($json) ? $json : '-';
	}

	private function assertSnapshotMatchesAction(AgentAction $action, AgentMutationCommitSnapshot $snapshot): void {
		if ($snapshot->getActionId() !== $action->getId()) {
			throw new \RuntimeException('Batch mutation snapshot belongs to a different action.');
		}
	}

	private function childCallId(string $parentId, int $index): string {
		return trim($parentId) . '.batch.' . str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT);
	}

	private function createAction(AiToolCall $call): AgentAction {
		return new AgentAction(
			$call->getId(),
			AgentAction::TYPE_TOOL_CALL,
			$call->getName(),
			$call->getArguments(),
			['tool_call' => $call->getMetadata()]
		);
	}

	/** @return ?array<string,mixed> */
	private function findDefinition(string $toolName, IAgentContext $context): ?array {
		$definitions = $context->getVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS);
		return is_array($definitions)
			? $this->definitionSemantics->findDefinition($definitions, $toolName)
			: null;
	}

	private function findTool(string $toolName, IAgentContext $context): ?IAgentTool {
		$tools = $context->getVar(AgentToolLoopContextKeys::TOOLS);
		if (!is_array($tools)) {
			return null;
		}
		foreach ($tools as $tool) {
			if (!$tool instanceof IAgentTool) {
				continue;
			}
			foreach ($tool->getToolDefinitions() as $definition) {
				if (is_array($definition) && $this->definitionSemantics->getToolName($definition) === $toolName) {
					return $tool;
				}
			}
		}
		return null;
	}

	private function hashData(mixed $value): string {
		$json = json_encode($this->canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($json)) {
			throw new \RuntimeException('Batch data could not be serialized.');
		}
		return hash('sha256', $json);
	}

	private function canonicalize(mixed $value): mixed {
		if (!is_array($value)) {
			return $value;
		}
		if (array_is_list($value)) {
			return array_map(fn(mixed $entry): mixed => $this->canonicalize($entry), $value);
		}
		ksort($value, SORT_STRING);
		foreach ($value as $key => $entry) {
			$value[$key] = $this->canonicalize($entry);
		}
		return $value;
	}
}
