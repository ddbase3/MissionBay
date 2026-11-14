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

namespace Base3\MissionBay\Test\Node\Core;

use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Node\Core\TestInputNode;
use PHPUnit\Framework\TestCase;

final class TestInputNodeTest extends TestCase {

	public function testPassesThroughNestedValuesWithoutModification(): void {
		$node = new TestInputNode();
		$input = ['items' => [0, false, null]];
		$this->assertSame(['value' => $input], $node->execute(['value' => $input], [], new AgentContext()));
	}

	public function testAbsentInputDefaultsToNull(): void {
		$node = new TestInputNode();
		$this->assertSame(['value' => null], $node->execute([], [], new AgentContext()));
	}
}
