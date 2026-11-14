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
use Base3\MissionBay\Node\Data\JsonToArrayNode;
use PHPUnit\Framework\TestCase;

final class JsonToArrayNodeTest extends TestCase {

	public function testNestedJsonDecodesAsAssociativeArray(): void {
		$node = new JsonToArrayNode();
		$this->assertSame(['array' => ['user' => ['id' => 5], 'enabled' => false]], $node->execute(['json' => '{"user":{"id":5},"enabled":false}'], [], new AgentContext()));
	}

	public function testValidJsonScalarsAreNotDiscarded(): void {
		$node = new JsonToArrayNode();
		$context = new AgentContext();
		$this->assertSame(['array' => null], $node->execute(['json' => 'null'], [], $context));
		$this->assertSame(['array' => false], $node->execute(['json' => 'false'], [], $context));
		$this->assertSame(['array' => [1, 2]], $node->execute(['json' => '[1,2]'], [], $context));
	}

	public function testInvalidJsonReturnsParserError(): void {
		$node = new JsonToArrayNode('decoder');
		$result = $node->execute(['json' => '{ broken'], [], new AgentContext());
		$this->assertArrayHasKey('error', $result);
		$this->assertStringContainsString('Invalid JSON', $result['error']);
		$this->assertStringContainsString('decoder', $result['error']);
	}

	public function testMissingJsonIsReportedAsError(): void {
		$node = new JsonToArrayNode();
		$this->assertArrayHasKey('error', $node->execute([], [], new AgentContext()));
	}
}
