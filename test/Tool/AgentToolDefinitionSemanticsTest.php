<?php declare(strict_types=1);

namespace MissionBay\Test\Tool;

use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use PHPUnit\Framework\TestCase;

final class AgentToolDefinitionSemanticsTest extends TestCase {

	public function testRequiresExplicitReadOnlyAnnotation(): void {
		$semantics = new AgentToolDefinitionSemantics();

		self::assertFalse($semantics->isExplicitReadOnlyDefinition([
			'function' => ['name' => 'legacy_tool']
		]));
		self::assertTrue($semantics->isExplicitReadOnlyDefinition([
			'readOnlyHint' => true,
			'mutation' => false,
			'function' => ['name' => 'read_tool']
		]));
		self::assertFalse($semantics->isExplicitReadOnlyDefinition([
			'readOnlyHint' => true,
			'mutation' => true,
			'function' => ['name' => 'mutating_tool']
		]));
	}
	public function testReadsBatchAnnotationsPerFunction(): void {
		$semantics = new AgentToolDefinitionSemantics();
		$definition = [
			'batchable' => true,
			'batchIndependent' => true,
			'maxBatchSize' => 12,
			'function' => ['name' => 'batch_target']
		];

		self::assertTrue($semantics->isBatchableDefinition($definition));
		self::assertTrue($semantics->isBatchIndependentDefinition($definition));
		self::assertSame(12, $semantics->getMaxBatchSize($definition));
	}

}
