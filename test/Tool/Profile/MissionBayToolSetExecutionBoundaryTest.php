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

namespace Base3\MissionBay\Test\Tool\Profile;

use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\MissionBay\Capability\AgentCapabilityCatalogBuilder;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use Base3\MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use Base3\MissionBay\Test\Profile\ProfileToolsTestAgentTool;
use Base3\MissionBay\Tool\Profile\MissionBayAgentToolSet;
use PHPUnit\Framework\TestCase;

final class MissionBayToolSetExecutionBoundaryTest extends TestCase {

	private function definition(string $name, array $annotations = ['readOnlyHint' => true], array $schema = []): array {
		return [
			'type' => 'function',
			'function' => [
				'name' => $name,
				'description' => 'Controlled test tool',
				'parameters' => $schema !== [] ? $schema : ['type' => 'object', 'properties' => [], 'additionalProperties' => true]
			],
			'annotations' => $annotations
		];
	}

	private function toolSet(array $definitions, ?AgentContext &$context = null, bool $withImplementation = true): MissionBayAgentToolSet {
		$tool = new ProfileToolsTestAgentTool($definitions);
		$catalog = (new AgentCapabilityCatalogBuilder())->build([$tool], $definitions);
		$context = new AgentContext(null, ['runtime_id' => 'run-one', 'conversation_id' => 'conversation-one']);
		$context->setVar('missionbay.agent.tools.definitions', $definitions);
		$semantics = new AgentToolDefinitionSemantics();
		$fingerprint = new AgentActionFingerprint();
		return new MissionBayAgentToolSet(
			$catalog,
			$withImplementation ? array_fill_keys($catalog->names(), $tool) : [],
			$withImplementation ? [$tool] : [],
			$context,
			new AgentToolContractValidationService(),
			$semantics,
			$fingerprint,
			new AgentMutationCommitGuardService($fingerprint, null, $semantics)
		);
	}

	private function mutationDefinition(bool $approval = true, bool $guard = false): array {
		return $this->definition('modify-record', ['mutation' => true, 'requiresApproval' => $approval, 'commitGuardRequired' => $guard]);
	}

	public function testUnknownToolReturnsStructuredFailure(): void {
		$set = $this->toolSet([]);
		$result = $set->execute('call-one', 'not-in-catalog', ['value' => 1]);
		$this->assertFalse($result->isSuccess());
		$this->assertSame('unknown_tool', $result->getErrorCode());
		$this->assertSame('call-one', $result->getCallId());
	}

	public function testDefinedToolWithoutExecutableImplementationReturnsFailure(): void {
		$set = $this->toolSet([$this->definition('lookup')], withImplementation: false);
		$result = $set->execute('call-one', 'lookup', []);
		$this->assertFalse($result->isSuccess());
		$this->assertSame('tool_not_executable', $result->getErrorCode());
	}

	public function testReadOnlyToolExecutesWithOriginalArguments(): void {
		$set = $this->toolSet([$this->definition('lookup')]);
		$result = $set->execute('call-one', 'lookup', ['query' => 'green']);
		$this->assertTrue($result->isSuccess());
		$this->assertSame('lookup', $result->getToolName());
		$this->assertSame(['query' => 'green'], $result->getArguments());
		$this->assertSame(['name' => 'lookup', 'arguments' => ['query' => 'green']], $result->getOutput());
	}

	public function testSuccessfulReadOnlyCallIncludesContractMetadata(): void {
		$set = $this->toolSet([$this->definition('lookup')]);
		$result = $set->execute('call-one', 'lookup', []);
		$this->assertArrayHasKey('input_contract', $result->getMetadata());
		$this->assertArrayHasKey('output_contract', $result->getMetadata());
		$this->assertFalse($result->getMetadata()['mutation']);
	}

	public function testInvalidInputIsRejectedBeforeExecution(): void {
		$schema = ['type' => 'object', 'properties' => ['count' => ['type' => 'integer']], 'required' => ['count']];
		$set = $this->toolSet([$this->definition('lookup', ['readOnlyHint' => true], $schema)]);
		$result = $set->execute('call-one', 'lookup', ['count' => 'invalid']);
		$this->assertFalse($result->isSuccess());
		$this->assertSame('tool_input_contract_violation', $result->getErrorCode());
		$this->assertArrayHasKey('input_contract', $result->getMetadata());
	}

	public function testInvalidOutputIsRejectedAfterExecution(): void {
		$definition = $this->definition('lookup');
		$definition['outputSchema'] = ['type' => 'object', 'properties' => ['result' => ['type' => 'string']], 'required' => ['result']];
		$set = $this->toolSet([$definition]);
		$result = $set->execute('call-one', 'lookup', []);
		$this->assertFalse($result->isSuccess());
		$this->assertSame('tool_output_contract_violation', $result->getErrorCode());
		$this->assertArrayHasKey('output_contract', $result->getMetadata());
	}

	public function testMutationNeverExecutesDirectlyWithoutApproval(): void {
		$set = $this->toolSet([$this->mutationDefinition()]);
		$result = $set->execute('call-one', 'modify-record', ['value' => 'restricted']);
		$this->assertFalse($result->isSuccess());
		$this->assertSame('approval_required', $result->getErrorCode());
	}

	public function testReadOnlyCallDoesNotNeedSuspension(): void {
		$set = $this->toolSet([$this->definition('lookup')]);
		$this->assertNull($set->prepareSuspension('call-one', 'lookup', []));
	}

	public function testUnknownCallDoesNotCreateApprovalRequest(): void {
		$set = $this->toolSet([]);
		$this->assertNull($set->prepareSuspension('call-one', 'unknown', []));
	}

	public function testMutationWithoutApprovalAnnotationCannotBeSuspended(): void {
		$set = $this->toolSet([$this->mutationDefinition(false)]);
		$this->expectException(\RuntimeException::class);
		$set->prepareSuspension('call-one', 'modify-record', []);
	}

	public function testApprovedMutationIsRepresentedByPendingSuspension(): void {
		$set = $this->toolSet([$this->mutationDefinition()]);
		$suspension = $set->prepareSuspension('call-one', 'modify-record', ['value' => 'restricted']);
		$this->assertNotNull($suspension);
		$this->assertCount(1, $suspension->getRequests());
		$this->assertSame('modify-record', $suspension->getRequests()[0]->getAction()->getName());
		$this->assertSame('call-one', $suspension->getRequests()[0]->getAction()->getId());
	}

	public function testApprovalRequestCarriesValidatedCallArguments(): void {
		$set = $this->toolSet([$this->mutationDefinition()]);
		$suspension = $set->prepareSuspension('call-one', 'modify-record', ['record' => 'A']);
		$this->assertSame(['record' => 'A'], $suspension->getRequests()[0]->getAction()->getInput());
		$this->assertArrayHasKey('input_contract', $suspension->getState());
	}

	public function testDeniedMutationReturnsExplicitRejection(): void {
		$set = $this->toolSet([$this->mutationDefinition()]);
		$suspension = $set->prepareSuspension('call-one', 'modify-record', []);
		$response = new AgentInteractionResponse($suspension->getRequests()[0]->getId(), AgentInteractionResponse::DECISION_DENY, [], 'Not allowed');
		$result = $set->resumeSuspension($suspension, $response);
		$this->assertFalse($result->isSuccess());
		$this->assertSame('action_declined_by_user', $result->getErrorCode());
		$this->assertSame('Not allowed', $result->getErrorMessage());
	}

	public function testUnrelatedResponseCannotResumeSuspension(): void {
		$set = $this->toolSet([$this->mutationDefinition()]);
		$suspension = $set->prepareSuspension('call-one', 'modify-record', []);
		$this->expectException(\RuntimeException::class);
		$set->resumeSuspension($suspension, new AgentInteractionResponse('foreign-request', AgentInteractionResponse::DECISION_APPROVE));
	}

	public function testApprovalCannotBeReplayedAcrossRuntimeBindings(): void {
		$context = null;
		$set = $this->toolSet([$this->mutationDefinition()], $context);
		$suspension = $set->prepareSuspension('call-one', 'modify-record', []);
		$context->setVar('runtime_id', 'other-run');
		$this->expectException(\RuntimeException::class);
		$set->resumeSuspension($suspension, new AgentInteractionResponse($suspension->getRequests()[0]->getId(), AgentInteractionResponse::DECISION_APPROVE));
	}

	public function testUnapprovedRevisionCannotBypassInputContract(): void {
		$schema = ['type' => 'object', 'properties' => ['count' => ['type' => 'integer']], 'required' => ['count']];
		$definition = $this->mutationDefinition();
		$definition['function']['parameters'] = $schema;
		$set = $this->toolSet([$definition]);
		$this->expectException(\RuntimeException::class);
		$set->prepareSuspension('call-one', 'modify-record', ['count' => 'not-an-integer']);
	}

	public function testExplicitApprovedMutationWithGuardOptOutExecutes(): void {
		$set = $this->toolSet([$this->mutationDefinition()]);
		$suspension = $set->prepareSuspension('call-one', 'modify-record', ['record' => 'safe']);
		$response = new AgentInteractionResponse($suspension->getRequests()[0]->getId(), AgentInteractionResponse::DECISION_APPROVE);
		$result = $set->resumeSuspension($suspension, $response);
		$this->assertTrue($result->isSuccess());
		$this->assertSame(['name' => 'modify-record', 'arguments' => ['record' => 'safe']], $result->getOutput());
	}
}
