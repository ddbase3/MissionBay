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

use Base3\AssistantFoundation\Dto\AgentStageResult;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\AgentStageResultAccumulator;
use PHPUnit\Framework\TestCase;

final class AgentStageResultAccumulatorTest extends TestCase {

	public function testPatchWritesIntoContextAndResult(): void {
		$context = new AgentContext(vars: ['untouched' => 1]);
		$accumulator = new AgentStageResultAccumulator($context);
		$accumulator->apply(AgentStageResult::patch(['status' => 'running', 'count' => 1]));
		$this->assertSame('running', $context->getVar('status'));
		$this->assertSame(1, $context->getVar('count'));
		$this->assertSame(1, $context->getVar('untouched'));
		$this->assertSame(['status' => 'running', 'count' => 1], $accumulator->result()->getPatch());
	}

	public function testLastPatchWinsAndEmptyResultPreservesPatch(): void {
		$context = new AgentContext();
		$accumulator = new AgentStageResultAccumulator($context);
		$accumulator->apply(AgentStageResult::patch(['status' => 'old', 'ignored' => null]));
		$accumulator->apply(AgentStageResult::none());
		$accumulator->apply(AgentStageResult::patch(['status' => 'new']));
		$this->assertSame(['status' => 'new', 'ignored' => null], $accumulator->result()->getPatch());
		$this->assertSame('new', $context->getVar('status'));
	}

	public function testMetadataIsFlattenedOrNamespacedWithoutModifyingContext(): void {
		$context = new AgentContext();
		$accumulator = new AgentStageResultAccumulator($context);
		$accumulator->apply(AgentStageResult::none(['provider' => 'first', 'attempts' => 1]));
		$accumulator->apply(AgentStageResult::none(['provider' => 'last']), 'decision');
		$accumulator->apply(AgentStageResult::none(['attempts' => 2]));
		$this->assertSame([
			'provider' => 'first',
			'attempts' => 2,
			'decision' => ['provider' => 'last']
		], $accumulator->result()->getMetadata());
		$this->assertSame([], $context->listVars());
	}
}
