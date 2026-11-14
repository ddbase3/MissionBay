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

namespace Base3\MissionBay\Test\Context;

use Base3\MissionBay\Context\SubFlowContext;
use PHPUnit\Framework\TestCase;

final class SubFlowContextTest extends TestCase {

	public function testExtraVariablesTakePrecedenceEvenWhenNull(): void {
		$context = new SubFlowContext(vars: ['one' => 'base', 'two' => 2], extraVars: ['one' => null, 'three' => false]);
		$this->assertSame('subflowcontext', SubFlowContext::getName());
		$this->assertNull($context->getVar('one'));
		$this->assertSame(2, $context->getVar('two'));
		$this->assertFalse($context->getVar('three'));
	}

	public function testStandardWritesCannotOverrideExtraVariables(): void {
		$context = new SubFlowContext(vars: ['key' => 'base'], extraVars: ['key' => 'extra']);
		$context->setVar('key', 'written');
		$this->assertSame('extra', $context->getVar('key'));
		$context->forgetVar('key');
		$this->assertSame('extra', $context->getVar('key'));
	}

	public function testUnknownVariableIsNullAndRegularWritesRemainVisible(): void {
		$context = new SubFlowContext();
		$this->assertNull($context->getVar('missing'));
		$context->setVar('new', ['ok' => true]);
		$this->assertSame(['ok' => true], $context->getVar('new'));
	}
}
