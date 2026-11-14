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

namespace Base3\MissionBay\Test\Tool;

use Base3\MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
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
