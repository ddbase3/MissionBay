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

use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Node\Data\TryArrayGetNode;
use PHPUnit\Framework\TestCase;

final class TryArrayGetNodeTest extends TestCase {

	public function testNestedValuesAndNullAreReturned(): void {
		$node = new TryArrayGetNode();
		$context = new AgentContext();
		$this->assertSame(['value' => 12], $node->execute(['array' => ['a' => ['b' => 12]], 'path' => 'a.b'], [], $context));
		$this->assertSame(['value' => null], $node->execute(['array' => ['a' => null], 'path' => 'a'], [], $context));
	}

	public function testMissingKeysAndScalarIntermediateValuesYieldNoOutput(): void {
		$node = new TryArrayGetNode();
		$context = new AgentContext();
		$this->assertSame([], $node->execute(['array' => ['a' => 4], 'path' => 'a.b'], [], $context));
		$this->assertSame([], $node->execute(['array' => [], 'path' => 'missing'], [], $context));
	}

	public function testWrongInputTypesAndMissingInputsYieldNoOutput(): void {
		$node = new TryArrayGetNode();
		$context = new AgentContext();
		$this->assertSame([], $node->execute(['array' => 'text', 'path' => 'a'], [], $context));
		$this->assertSame([], $node->execute(['array' => [], 'path' => []], [], $context));
		$this->assertSame([], $node->execute([], [], $context));
	}
}
