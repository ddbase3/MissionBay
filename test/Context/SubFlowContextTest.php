<?php declare(strict_types=1);

namespace MissionBay\Test\Context;

use MissionBay\Context\SubFlowContext;
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
