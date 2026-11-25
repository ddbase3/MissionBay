<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Orchestrator\Fixture;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionReview;
use Base3\AssistantFoundation\Dto\AgentMutationCommitDecision;
use Base3\AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use Base3\CoreFoundation\Api\IOutputSchemaProvider;
use Base3\MissionBay\Api\IAgentCapabilitySourceMetadata;
use Base3\MissionBay\Api\IAgentMutationGuardedTool;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Test\Orchestrator\ApprovalMutationTool;

final class ProgramToolLoopFixtureTool implements IAgentTool, IAgentCapabilitySourceMetadata, IOutputSchemaProvider, IAgentMutationGuardedTool {
	public int $reads = 0;
	public bool $invalidRead = false;
	public readonly ApprovalMutationTool $mutation;

	public function __construct() { $this->mutation = new ApprovalMutationTool(); }
	public static function getName(): string { return 'programtoolloopfixturetool'; }
	public function getCapabilitySourceId(): string { return 'program-fixture'; }
	public function getCapabilitySourceLabel(): string { return 'Program fixture'; }
	public function getCapabilitySourceDescription(): string { return 'Read and update records.'; }
	public function getToolDefinitions(): array {
		return array_merge([[
			'type' => 'function', 'readOnlyHint' => true,
			'function' => ['name' => 'read_records', 'description' => 'Read records.', 'parameters' => ['type' => 'object', 'properties' => [], 'additionalProperties' => false]]
		]], $this->mutation->getToolDefinitions());
	}
	public function getOutputSchemas(): array {
		return [
			'read_records' => ['type' => 'object', 'required' => ['items'], 'properties' => ['items' => ['type' => 'array', 'items' => ['type' => 'integer']]]],
			'update_record' => ['type' => 'object', 'required' => ['updated'], 'properties' => ['updated' => ['type' => 'boolean']]]
		];
	}
	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		if ($name === 'read_records') { $this->reads++; return ['items' => $this->invalidRead ? 'invalid' : [42, 43]]; }
		return $this->mutation->callTool($name, $arguments, $context);
	}
	public function captureMutationCommitSnapshot(AgentAction $action, string $actionFingerprint, IAgentContext $context): AgentMutationCommitSnapshot {
		return $this->mutation->captureMutationCommitSnapshot($action, $actionFingerprint, $context);
	}
	public function getActionReview(AgentAction $action, AgentMutationCommitSnapshot $snapshot, IAgentContext $context): AgentActionReview {
		return $this->mutation->getActionReview($action, $snapshot, $context);
	}
	public function validateMutationCommit(AgentAction $action, AgentMutationCommitSnapshot $snapshot, IAgentContext $context): AgentMutationCommitDecision {
		return $this->mutation->validateMutationCommit($action, $snapshot, $context);
	}
}
