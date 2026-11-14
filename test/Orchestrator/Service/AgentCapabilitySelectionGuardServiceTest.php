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

namespace Base3\MissionBay\Test\Orchestrator\Service;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentCapabilitySelectionGuardServiceTest extends TestCase {

	public function testUnselectedToolsAreAllowedWithoutExplicitEnforcement(): void {
		$guard = new AgentCapabilitySelectionGuardService();
		foreach ([null, false, 1, 'true'] as $flag) {
			$context = $this->context([Keys::CAPABILITY_SELECTION_APPLIED => $flag]);
			$this->assertFalse($guard->isEnforced($context));
			$this->assertTrue($guard->isAllowed($context, 'unknown'));
		}
	}

	public function testEnforcedSelectionOnlyAllowsExactToolName(): void {
		$guard = new AgentCapabilitySelectionGuardService();
		$context = $this->context([
			Keys::CAPABILITY_SELECTION_APPLIED => true,
			Keys::SELECTED_TOOL_NAMES => ['list', 'fetch']
		]);
		$this->assertTrue($guard->isEnforced($context));
		$this->assertTrue($guard->isAllowed($context, 'fetch'));
		$this->assertFalse($guard->isAllowed($context, 'Fetch'));
		$this->assertFalse($guard->isAllowed($context, 'delete'));
	}

	public function testEnforcedInvalidSelectionsDenyAllTools(): void {
		$guard = new AgentCapabilitySelectionGuardService();
		foreach ([null, '', false, 'read', 12] as $selected) {
			$context = $this->context([
				Keys::CAPABILITY_SELECTION_APPLIED => true,
				Keys::SELECTED_TOOL_NAMES => $selected
			]);
			$this->assertFalse($guard->isAllowed($context, 'read'));
		}
	}

	public function testFailurePreservesCallAndExposesRetryableMetadataWithoutExecuting(): void {
		$guard = new AgentCapabilitySelectionGuardService();
		$call = new AiToolCall('id-12', 'delete', ['record' => 7]);
		$context = $this->context([Keys::SELECTED_TOOL_NAMES => ['read', 'search']]);
		$result = $guard->createFailure($context, $call, 4);
		$this->assertFalse($result->isSuccess());
		$this->assertSame('id-12', $result->getCallId());
		$this->assertSame('delete', $result->getToolName());
		$this->assertSame(['record' => 7], $result->getArguments());
		$this->assertSame('capability_not_selected', $result->getErrorCode());
		$this->assertSame(['iteration' => 4, 'selection_size' => 2, 'retryable' => true], $result->getMetadata());
	}

	public function testFailureHandlesAbsentOrInvalidSelectionAsEmpty(): void {
		$guard = new AgentCapabilitySelectionGuardService();
		foreach ([null, false, 'read'] as $selected) {
			$result = $guard->createFailure($this->context([Keys::SELECTED_TOOL_NAMES => $selected]), new AiToolCall('x', 'write'), 0);
			$this->assertSame(0, $result->getMetadata()['selection_size']);
		}
	}

	/** @param array<string,mixed> $values */
	private function context(array $values): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? null);
		return $context;
	}
}
