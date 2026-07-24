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
}
