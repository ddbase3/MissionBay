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

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\MissionBay\Node\Control\ConditionalPassNode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ConditionalPassNodeExecutionTest extends TestCase {

	private array $previousSession = [];
	private bool $hadSession = false;

	protected function setUp(): void {
		$this->hadSession = isset($_SESSION) && is_array($_SESSION);
		$this->previousSession = $this->hadSession ? $_SESSION : [];
		if(session_status() !== PHP_SESSION_ACTIVE) {
			session_start();
		}
	}

	protected function tearDown(): void {
		if($this->hadSession) {
			$_SESSION = $this->previousSession;
		} else {
			unset($_SESSION);
		}
	}

	public function testMissingVariableNameReturnsErrorWithoutRouting(): void {
		$this->assertSame(['error' => 'No varname provided'], $this->node()->execute(['input' => 'sample'], [], $this->context()));
	}

	public function testEmptyVariableNameReturnsError(): void {
		$this->assertSame(['error' => 'No varname provided'], $this->node()->execute(['varname' => ''], [], $this->context()));
	}

	public function testMatchingSessionValueRoutesToBlocked(): void {
		$_SESSION['mb_conditional_pass_unit'] = 'deny';
		$this->assertSame(['blocked' => ['id' => 12]], $this->node()->execute([
			'varname' => 'mb_conditional_pass_unit', 'expected' => 'deny', 'input' => ['id' => 12]
		], [], $this->context()));
	}

	public function testDifferentSessionValueRoutesToPassed(): void {
		$_SESSION['mb_conditional_pass_unit'] = 'allow';
		$this->assertSame(['passed' => 'payload'], $this->node()->execute([
			'varname' => 'mb_conditional_pass_unit', 'expected' => 'deny', 'input' => 'payload'
		], [], $this->context()));
	}

	public function testMissingSessionVariableDoesNotMatchSpecifiedExpectedValue(): void {
		unset($_SESSION['mb_conditional_pass_unit']);
		$this->assertSame(['passed' => false], $this->node()->execute([
			'varname' => 'mb_conditional_pass_unit', 'expected' => 'deny', 'input' => false
		], [], $this->context()));
	}

	public function testMissingSessionVariableMatchesNullExpectedValue(): void {
		unset($_SESSION['mb_conditional_pass_unit']);
		$this->assertSame(['blocked' => null], $this->node()->execute([
			'varname' => 'mb_conditional_pass_unit', 'expected' => null
		], [], $this->context()));
	}

	public function testDifferentScalarTypesDoNotMatchStrictly(): void {
		$_SESSION['mb_conditional_pass_unit'] = 10;
		$this->assertSame(['passed' => 10], $this->node()->execute([
			'varname' => 'mb_conditional_pass_unit', 'expected' => '10', 'input' => 10
		], [], $this->context()));
	}

	public function testPortsRepresentBothOutcomesAndError(): void {
		$node = $this->node();
		$this->assertSame(['input', 'varname', 'expected'], array_map(static fn($port): string => $port->name, $node->getInputDefinitions()));
		$this->assertSame(['passed', 'blocked', 'error'], array_map(static fn($port): string => $port->name, $node->getOutputDefinitions()));
		$this->assertSame('conditionalpassnode', $node::getName());
	}

	private function node(): ConditionalPassNode {
		return new ConditionalPassNode('cond-unit');
	}

	private function context(): IAgentContext {
		return $this->createStub(IAgentContext::class);
	}
}
