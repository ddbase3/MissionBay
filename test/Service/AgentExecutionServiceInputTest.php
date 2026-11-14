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

namespace Base3\MissionBay\Test\Service;

use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\MissionBay\Api\IAgentContextFactory;
use Base3\MissionBay\Api\IAgentFlow;
use Base3\MissionBay\Api\IAgentFlowCompiler;
use Base3\MissionBay\Api\IAgentFlowFactory;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\AgentFlowCompilation;
use Base3\MissionBay\Service\AgentExecutionService;
use PHPUnit\Framework\TestCase;

final class AgentExecutionServiceInputTest extends TestCase {

	public function testLegacyUserInputBecomesPromptWithoutDuplication(): void {
		$service = $this->service(['nodes' => [], 'connections' => []], function(array $inputs): void {
			$this->assertSame('legacy prompt', $inputs['prompt']);
			$this->assertArrayNotHasKey('user', $inputs);
		});
		$this->assertSame(['ok' => true], $service->execute(new AgentExecutionRequest([], ['user' => 'legacy prompt']))->getOutput());
	}

	public function testCanonicalPromptHasPrecedenceOverLegacyInput(): void {
		$service = $this->service(['nodes' => [], 'connections' => []], function(array $inputs): void {
			$this->assertSame('canonical', $inputs['prompt']);
			$this->assertArrayNotHasKey('user', $inputs);
		});
		$service->execute(new AgentExecutionRequest([], ['prompt' => 'canonical', 'user' => 'legacy']));
	}

	public function testInitialContextVariablesAreCopiedAndWhitespaceNamesAreIgnored(): void {
		$context = new AgentContext();
		$service = $this->service(['nodes' => [], 'connections' => []], null, $context);
		$service->execute(new AgentExecutionRequest([], [], [' name ' => 'value', '  ' => 'ignored', 5 => false]));
		$this->assertSame('value', $context->getVar('name'));
		$this->assertSame(false, $context->getVar('5'));
		$this->assertNull($context->getVar(''));
	}

	public function testMissingAssistantCreatesOneWarningForEachRequestedInput(): void {
		$service = $this->service(['nodes' => [], 'connections' => []]);
		$result = $service->execute(new AgentExecutionRequest([], ['mode' => 'suggestions', 'resume' => ['handle' => 'x']]));
		$this->assertSame([
			'Assistant node not found for input connection: mode',
			'Assistant node not found for input connection: resume'
		], $result->getWarnings());
	}

	public function testExistingConnectionsAreNotDuplicated(): void {
		$connection = ['from' => '__input__', 'output' => 'mode', 'to' => 'assistant', 'input' => 'mode'];
		$service = $this->service(['nodes' => [['id' => 'assistant']], 'connections' => [$connection]], null, null, function(array $flow) use ($connection): void {
			$this->assertSame([$connection], $flow['connections']);
		});
		$result = $service->execute(new AgentExecutionRequest([], ['mode' => 'suggestions']));
		$this->assertSame([], $result->getWarnings());
	}

	public function testNewConnectionsAndCompilerWarningsPreserveOrder(): void {
		$service = $this->service(['nodes' => [['id' => 'assistant']], 'connections' => []], null, null, function(array $flow): void {
			$this->assertSame(['mode', 'resume'], array_column($flow['connections'], 'input'));
		}, ['warning-a', 'warning-a', 'warning-b']);
		$result = $service->execute(new AgentExecutionRequest([], ['mode' => 'suggestions', 'resume' => []]));
		$this->assertSame(['warning-a', 'warning-b'], $result->getWarnings());
	}

	public function testEventSinkIsInjectedIntoExecutionContext(): void {
		$context = new AgentContext();
		$service = $this->service(['nodes' => [], 'connections' => []], null, $context);
		$sink = $this->createStub(IAgentEventSink::class);
		$service->execute(new AgentExecutionRequest([]), $sink);
		$this->assertSame($sink, $context->getVar(IAgentEventSink::CONTEXT_KEY));
	}

	private function service(array $definition, ?callable $runCheck = null, ?AgentContext $context = null, ?callable $flowCheck = null, array $warnings = []): AgentExecutionService {
		$context ??= new AgentContext();
		$contextFactory = $this->createStub(IAgentContextFactory::class);
		$contextFactory->method('createContext')->willReturn($context);
		$flow = $this->createStub(IAgentFlow::class);
		$flow->method('run')->willReturnCallback(static function(array $inputs) use ($runCheck): array {
			if ($runCheck !== null) {
				$runCheck($inputs);
			}
			return ['ok' => true];
		});
		$flowFactory = $this->createStub(IAgentFlowFactory::class);
		$flowFactory->method('createFromArray')->willReturnCallback(static function(string $name, array $compiled, $resolvedContext) use ($flow, $context, $flowCheck): IAgentFlow {
			if ($name !== 'strictflow' || $resolvedContext !== $context) {
				throw new \RuntimeException('Incorrect flow context or flow type.');
			}
			if ($flowCheck !== null) {
				$flowCheck($compiled);
			}
			return $flow;
		});
		$compiler = $this->createStub(IAgentFlowCompiler::class);
		$compiler->method('compile')->willReturn(new AgentFlowCompilation($definition, $warnings));
		return new AgentExecutionService($contextFactory, $flowFactory, $compiler);
	}
}
