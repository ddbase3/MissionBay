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

namespace Base3\MissionBay\Test\Service;

use Base3\AssistantFoundation\Api\IAgentActionPolicy;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentSuspensionRepository;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionReview;
use Base3\AssistantFoundation\Dto\AgentMutationCommitDecision;
use Base3\AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use Base3\AssistantFoundation\Dto\AgentSuspension;
use Base3\AssistantFoundation\Dto\AgentSuspensionClaim;
use Base3\AssistantFoundation\Dto\AgentSuspensionResolution;
use Base3\AssistantFoundation\Dto\AgentSuspensionState;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\Api\IAgentMutationGuardedTool;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Policy\IAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Service\AgentActionReviewService;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentInteractionResponseResolver;
use Base3\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use Base3\MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use Base3\MissionBay\Orchestrator\Validation\JsonSchemaValidator;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use Base3\MissionBay\Policy\MutationApprovalAgentActionPolicy;
use Base3\MissionBay\Service\AgentComponentPresetToolTestService;
use PHPUnit\Framework\TestCase;

final class ComponentPresetApprovalMutationTool implements IAgentTool, IAgentMutationGuardedTool {

	private int $callCount = 0;

	/** @var array<string,mixed> */
	private array $lastArguments = [];

	public static function getName(): string {
		return 'componentpresetapprovalmutationtool';
	}

	public function getDescription(): string {
		return 'Mutation tool used by the component preset tester regression test.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'mutation' => true,
			'requiresApproval' => true,
			'commitGuardRequired' => true,
			'function' => [
				'name' => 'set_value',
				'description' => 'Sets a value.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'value' => ['type' => 'string']
					],
					'required' => ['value'],
					'additionalProperties' => false
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->callCount++;
		$this->lastArguments = $arguments;

		return [
			'ok' => true,
			'value' => $arguments['value'] ?? null
		];
	}

	public function captureMutationCommitSnapshot(
		AgentAction $action,
		string $actionFingerprint,
		IAgentContext $context
	): AgentMutationCommitSnapshot {
		return new AgentMutationCommitSnapshot($action->getId(), $actionFingerprint);
	}

	public function getActionReview(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentActionReview {
		return new AgentActionReview(
			'Change test value',
			'The configured test value will be changed.',
			['New value' => (string)($action->getInput()['value'] ?? '')]
		);
	}

	public function validateMutationCommit(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentMutationCommitDecision {
		return AgentMutationCommitDecision::allow('Mutation remains valid.');
	}

	public function getCallCount(): int {
		return $this->callCount;
	}

	/** @return array<string,mixed> */
	public function getLastArguments(): array {
		return $this->lastArguments;
	}
}
