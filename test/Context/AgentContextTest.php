<?php declare(strict_types=1);

namespace MissionBay\Test\Context;

use AssistantFoundation\Dto\AgentResult;
use AssistantFoundation\Dto\AgentState;
use MissionBay\Context\AgentContext;
use MissionBay\Memory\NoMemory;
use PHPUnit\Framework\TestCase;

final class AgentContextTest extends TestCase {

	public function testDefaultsToEmptyStateAndNoMemory(): void {
		$context = new AgentContext();
		$this->assertSame('agentcontext', AgentContext::getName());
		$this->assertSame(NoMemory::class, $context->getMemory()::class);
		$this->assertSame([], $context->listVars());
		$this->assertFalse($context->isFinished());
		$this->assertNull($context->getResult());
	}

	public function testVariableOverwriteNullAndForget(): void {
		$context = new AgentContext(vars: ['one' => 1]);
		$context->setVar('two', ['x' => 2]);
		$context->setVar('one', false);
		$context->setVar('nullable', null);
		$this->assertSame(false, $context->getVar('one'));
		$this->assertSame(['x' => 2], $context->getVar('two'));
		$this->assertSame(null, $context->getVar('nullable'));
		$this->assertSame(['one', 'two', 'nullable'], $context->listVars());
		$context->forgetVar('one');
		$context->forgetVar('missing');
		$this->assertSame(['two', 'nullable'], $context->listVars());
	}

	public function testExplicitMemoryAndStateAreRetained(): void {
		$memory = new NoMemory();
		$state = AgentState::empty();
		$context = new AgentContext($memory, ['a' => 'b'], $state);
		$this->assertSame($memory, $context->getMemory());
		$this->assertSame($state, $context->getState());
		$replacement = new NoMemory();
		$context->setMemory($replacement);
		$this->assertSame($replacement, $context->getMemory());
	}

	public function testResetStateLeavesVariablesAndClearsCompletion(): void {
		$context = new AgentContext(vars: ['keep' => 9]);
		$result = new AgentResult(\AssistantFoundation\Dto\AgentExecutionStatus::COMPLETED, AgentState::empty());
		$context->finish($result);
		$this->assertTrue($context->isFinished());
		$this->assertSame($result, $context->getResult());
		$state = AgentState::empty();
		$context->setState($state);
		$this->assertSame($state, $context->getState());
		$this->assertNull($context->getResult());
		$this->assertFalse($context->isFinished());
		$this->assertSame(9, $context->getVar('keep'));
	}
}
