<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Dto\AgentStageResult;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\AgentStageResultAccumulator;
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
