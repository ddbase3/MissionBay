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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Dto\AgentStageMount;
use Base3\AssistantFoundation\Dto\AgentStageSlot;
use Base3\MissionBay\Orchestrator\AgentStagePipelineResolver;
use PHPUnit\Framework\TestCase;

final class AgentStagePipelineResolverSafetyCoverageTest extends TestCase {

	private const CORE = ['model-decision', 'action-policy', 'tool-execution', 'tool-observation'];

	public function testEmptyDefaultStagePipelineIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		new AgentStagePipelineResolver(new RecordingAgentStageComponentResolver([]), []);
	}

	public function testMissingRequiredDefaultStageIsRejectedDuringConstruction(): void {
		$this->expectException(\RuntimeException::class);
		new AgentStagePipelineResolver(new RecordingAgentStageComponentResolver([]), ['model-decision']);
	}

	public function testUnknownDefaultStageIsRejectedDuringConstruction(): void {
		$this->expectException(\RuntimeException::class);
		new AgentStagePipelineResolver(new RecordingAgentStageComponentResolver([]), [...self::CORE, 'unknown-stage']);
	}

	public function testDuplicateDefaultStageIsRejectedDuringConstruction(): void {
		$this->expectException(\RuntimeException::class);
		new AgentStagePipelineResolver(new RecordingAgentStageComponentResolver([]), ['model-decision', 'model-decision']);
	}

	public function testNonStringStageIdentifiersAreRejectedBeforeResolution(): void {
		$this->expectException(\RuntimeException::class);
		$this->resolver()->resolve(['model-decision', 'action-policy', 5, 'tool-execution', 'tool-observation']);
	}

	public function testBlankStageIdentifiersAreRejectedBeforeResolution(): void {
		$this->expectException(\RuntimeException::class);
		$this->resolver()->resolve(['model-decision', 'action-policy', ' ', 'tool-execution', 'tool-observation']);
	}

	public function testRequiredStageCannotBeRemovedInExplicitConfiguration(): void {
		$this->expectException(\RuntimeException::class);
		$this->resolver()->resolve(['model-decision', 'action-policy', 'tool-observation']);
	}

	public function testMinimalRequiredStageConfigurationIsExecutable(): void {
		$this->assertSame(self::CORE, $this->ids($this->resolver()->resolve()));
	}

	public function testUnknownExplicitStageCannotBypassCanonicalOrder(): void {
		$this->expectException(\RuntimeException::class);
		$this->resolver()->resolve(['model-decision', 'action-policy', 'tool-execution', 'tool-observation', 'not-canonical']);
	}

	public function testModuleMountsBeforePlanningExecutionAndToolCallInStableOrder(): void {
		$resolver = $this->resolver();
		$stages = $resolver->resolve([], [
			new AgentStageMount(AgentStageSlot::PLANNING, new RecordingResolvedAgentStage('planning')),
			new AgentStageMount(AgentStageSlot::BEFORE_PLANNING, new RecordingResolvedAgentStage('preplan')),
			new AgentStageMount(AgentStageSlot::BEFORE_EXECUTION, new RecordingResolvedAgentStage('preexec')),
			new AgentStageMount(AgentStageSlot::EXECUTION, new RecordingResolvedAgentStage('execute')),
			new AgentStageMount(AgentStageSlot::AFTER_TOOL_CALL, new RecordingResolvedAgentStage('observe'))
		]);
		$this->assertSame([
			'preplan', 'planning', 'model-decision', 'preexec', 'action-policy',
			'execute', 'tool-execution', 'observe', 'tool-observation'
		], $this->ids($stages));
	}

	public function testMountsWithSameOrderAreSortedByStageId(): void {
		$stages = $this->resolver()->resolve([], [
			new AgentStageMount(AgentStageSlot::BEFORE_TOOL_CALL, new RecordingResolvedAgentStage('zeta'), 5),
			new AgentStageMount(AgentStageSlot::BEFORE_TOOL_CALL, new RecordingResolvedAgentStage('alpha'), 5)
		]);
		$this->assertSame([
			'model-decision', 'action-policy', 'alpha', 'zeta', 'tool-execution', 'tool-observation'
		], $this->ids($stages));
	}

	public function testMountAfterFinalAnswerNeedsSemanticVerificationStage(): void {
		$this->expectException(\RuntimeException::class);
		$this->resolver()->resolve([], [
			new AgentStageMount(AgentStageSlot::AFTER_FINAL_ANSWER, new RecordingResolvedAgentStage('postanswer'))
		]);
	}

	public function testFinalAnswerMountsUseSemanticVerificationAnchor(): void {
		$stageIds = [...self::CORE, 'semantic-verification'];
		$stages = $this->resolver($stageIds)->resolve([], [
			new AgentStageMount(AgentStageSlot::BEFORE_FINAL_ANSWER, new RecordingResolvedAgentStage('prefinal')),
			new AgentStageMount(AgentStageSlot::AFTER_FINAL_ANSWER, new RecordingResolvedAgentStage('postfinal'))
		]);
		$this->assertSame([
			...self::CORE, 'prefinal', 'semantic-verification', 'postfinal'
		], $this->ids($stages));
	}

	public function testDuplicateMountedStageIdentifiersAreRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->resolver()->resolve([], [
			new AgentStageMount(AgentStageSlot::BEFORE_TOOL_CALL, new RecordingResolvedAgentStage('duplicate')),
			new AgentStageMount(AgentStageSlot::AFTER_TOOL_CALL, new RecordingResolvedAgentStage('duplicate'))
		]);
	}

	public function testMountedStageCannotReuseCoreStageId(): void {
		$this->expectException(\RuntimeException::class);
		$this->resolver()->resolve([], [
			new AgentStageMount(AgentStageSlot::BEFORE_TOOL_CALL, new RecordingResolvedAgentStage('model-decision'))
		]);
	}

	public function testBlankMountedStageIdIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->resolver()->resolve([], [
			new AgentStageMount(AgentStageSlot::AFTER_TOOL_CALL, new RecordingResolvedAgentStage(' '))
		]);
	}

	public function testNonMountObjectsAreRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->resolver()->resolve([], ['invalid']);
	}

	public function testUnrecognizedSlotIsRejectedBeforeMounting(): void {
		$this->expectException(\InvalidArgumentException::class);
		new AgentStageMount('unknown_slot', new RecordingResolvedAgentStage('stage'));
	}

	private function resolver(array $stageIds = self::CORE): AgentStagePipelineResolver {
		$stages = [];
		foreach ($stageIds as $id) {
			$stages[$id] = new RecordingResolvedAgentStage($id);
		}
		return new AgentStagePipelineResolver(new RecordingAgentStageComponentResolver($stages), $stageIds);
	}

	/** @param array<int,IAgentStage> $stages */
	private function ids(array $stages): array {
		return array_map(static fn(IAgentStage $stage): string => $stage->id(), $stages);
	}
}
