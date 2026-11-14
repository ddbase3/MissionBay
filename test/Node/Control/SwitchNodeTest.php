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
use Base3\MissionBay\Node\Control\SwitchNode;
use PHPUnit\Framework\TestCase;

final class SwitchNodeTest extends TestCase {

	public function testExplicitCaseMatchesStrictly(): void {
		$node = new SwitchNode();
		$context = new AgentContext();
		$this->assertSame(['blue' => 1], $node->execute(['value' => 'blue', 'cases' => ['red', 'blue']], [], $context));
		$this->assertSame(['default' => 1], $node->execute(['value' => '1', 'cases' => [1]], [], $context));
	}

	public function testUnmatchedCaseGoesToDefault(): void {
		$node = new SwitchNode();
		$this->assertSame(['default' => 1], $node->execute(['value' => 'green', 'cases' => ['red']], [], new AgentContext()));
	}

	public function testFailSentinelWorksOnlyWithEmptyCaseList(): void {
		$node = new SwitchNode();
		$this->assertSame(['fail' => 1], $node->execute(['value' => 'fail'], [], new AgentContext()));
		$this->assertSame(['default' => 1], $node->execute(['value' => 'fail', 'cases' => ['other']], [], new AgentContext()));
	}

	public function testMissingValueAndInvalidCasesAreHandled(): void {
		$node = new SwitchNode('route');
		$this->assertStringContainsString('must be string', $node->execute(['value' => 5], [], new AgentContext())['error']);
		$this->assertSame(['default' => 1], $node->execute(['value' => 'x', 'cases' => 'x'], [], new AgentContext()));
	}
}
