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
use Base3\MissionBay\Node\Core\SetContextVarNode;
use PHPUnit\Framework\TestCase;

final class SetContextVarNodeTest extends TestCase {

	public function testWritesAnyValueToSharedContext(): void {
		$node = new SetContextVarNode();
		$context = new AgentContext();
		$this->assertSame(['success' => true], $node->execute(['key' => 'data', 'value' => [1, 2]], [], $context));
		$this->assertSame([1, 2], $context->getVar('data'));
		$this->assertSame(['success' => true], $node->execute(['key' => 'data', 'value' => false], [], $context));
		$this->assertSame(false, $context->getVar('data'));
	}

	public function testOmittedValueWritesNull(): void {
		$context = new AgentContext();
		$result = (new SetContextVarNode())->execute(['key' => 'unset'], [], $context);
		$this->assertSame(['success' => true], $result);
		$this->assertSame(['unset'], $context->listVars());
		$this->assertNull($context->getVar('unset'));
	}

	public function testInvalidKeyDoesNotModifyContext(): void {
		$context = new AgentContext();
		$result = (new SetContextVarNode())->execute(['key' => 7, 'value' => 'secret'], [], $context);
		$this->assertStringContainsString('must be a string', $result['error']);
		$this->assertSame([], $context->listVars());
	}
}
