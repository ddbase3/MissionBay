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

namespace Base3\MissionBay\Test\Node\Control;

use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Node\Control\IfNode;
use PHPUnit\Framework\TestCase;

final class IfNodeTest extends TestCase {

	public function testTrueAndFalseRouteToDifferentPorts(): void {
		$node = new IfNode('decision');
		$context = new AgentContext();
		$this->assertSame(['true' => 1], $node->execute(['condition' => true], [], $context));
		$this->assertSame(['false' => 1], $node->execute(['condition' => false], [], $context));
	}

	public function testAbsentConditionProducesContextualError(): void {
		$result = (new IfNode('decision'))->execute([], [], new AgentContext());
		$this->assertSame(['error' => '[ ifnode | decision ] Missing "condition" input'], $result);
	}

	public function testNonBooleanValuesAreRejectedWithoutCoercion(): void {
		$node = new IfNode('gate');
		foreach ([0, 1, '', 'false', null, []] as $invalid) {
			$this->assertStringContainsString('must be boolean', $node->execute(['condition' => $invalid], [], new AgentContext())['error']);
		}
	}

	public function testPortsAndTechnicalNameDescribeBranchOutputs(): void {
		$node = new IfNode();
		$this->assertSame('ifnode', IfNode::getName());
		$this->assertSame(['condition'], array_map(static fn($port) => $port->name, $node->getInputDefinitions()));
		$this->assertSame(['true', 'false', 'error'], array_map(static fn($port) => $port->name, $node->getOutputDefinitions()));
	}
}
