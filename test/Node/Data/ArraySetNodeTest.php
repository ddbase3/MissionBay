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

namespace Base3\MissionBay\Test\Node\Data;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\MissionBay\Node\Data\ArraySetNode;
use PHPUnit\Framework\TestCase;

final class ArraySetNodeTest extends TestCase {

	public function testCreatesNestedPathWithoutMutatingInput(): void {
		$input = ['profile' => ['id' => 4]];
		$node = new ArraySetNode('setter');
		$result = $node->execute(['array' => $input, 'path' => 'profile.name.first', 'value' => 'Ada'], [], $this->context());
		$this->assertSame(['profile' => ['id' => 4, 'name' => ['first' => 'Ada']]], $result['array']);
		$this->assertSame(['profile' => ['id' => 4]], $input);
	}

	public function testReplacesScalarIntermediatePathWithNewArray(): void {
		$result = (new ArraySetNode())->execute(['array' => ['user' => 'old'], 'path' => 'user.status', 'value' => false], [], $this->context());
		$this->assertSame(['user' => ['status' => false]], $result['array']);
	}

	public function testOverwritesExistingLeafWithNull(): void {
		$result = (new ArraySetNode())->execute(['array' => ['a' => ['b' => 1]], 'path' => 'a.b', 'value' => null], [], $this->context());
		$this->assertSame(['a' => ['b' => null]], $result['array']);
	}

	public function testRejectsNonArrayInputAndBlankPath(): void {
		$node = new ArraySetNode('set-id');
		$invalidArray = $node->execute(['array' => 'bad', 'path' => 'x'], [], $this->context());
		$invalidPath = $node->execute(['array' => [], 'path' => ''], [], $this->context());
		$this->assertStringContainsString('Input "array"', $invalidArray['error']);
		$this->assertStringContainsString('Input "path"', $invalidPath['error']);
		$this->assertStringContainsString('set-id', $invalidPath['error']);
	}

	public function testDeclaresExpectedPortsAndDescription(): void {
		$node = new ArraySetNode('set');
		$this->assertSame('arraysetnode', $node::getName());
		$this->assertCount(3, $node->getInputDefinitions());
		$this->assertCount(2, $node->getOutputDefinitions());
		$this->assertNotEmpty($node->getDescription());
	}

	private function context(): IAgentContext {
		return $this->createStub(IAgentContext::class);
	}
}
