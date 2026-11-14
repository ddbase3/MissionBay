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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentStageResult;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Api\IContainer;
use Base3\MissionBay\MissionBayPlugin;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentStateSynchronizer;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Service\AgentBudgetGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentLoopProgressService;
use Base3\MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use Base3\MissionBay\Orchestrator\Suspension\UnavailableAgentSuspensionRepository;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentToolOrchestratorTimingLogTest extends TestCase {

	public function testPluginCompositionUsesRegisteredLoggerWithoutPerRunLogger(): void {
		$services = [];
		$container = $this->createStub(IContainer::class);
		$container->method('set')->willReturnCallback(
			static function(string $name, mixed $definition) use (&$services, $container): IContainer {
				$services[$name] = $definition;
				return $container;
			}
		);
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::once())->method('log')->with(
			'agenttoolorchestrator',
			self::stringStartsWith('[TIMING] ')
		)->willReturn(true);
		$container->method('get')->willReturnCallback(
			static function(string $name) use ($logger): mixed {
				return match ($name) {
					ILogger::class => $logger,
					AgentActionResumeService::class => new AgentActionResumeService(
						new AgentActionFingerprint(),
						new UnavailableAgentSuspensionRepository()
					),
					AgentBudgetGuardService::class => new AgentBudgetGuardService(),
					AgentLoopProgressService::class => new AgentLoopProgressService(),
					AgentStateSynchronizer::class => new AgentStateSynchronizer(),
					AgentToolDefinitionSemantics::class => new AgentToolDefinitionSemantics(),
					default => throw new \LogicException('Unexpected service ' . $name)
				};
			}
		);

		(new MissionBayPlugin($container))->init();
		self::assertArrayHasKey(AgentToolOrchestrator::class, $services);
		$orchestrator = $services[AgentToolOrchestrator::class]($container);
		self::assertInstanceOf(AgentToolOrchestrator::class, $orchestrator);

		$result = $orchestrator->run(
			$this->createStub(IAiChatModel::class),
			[], [], [], new TimingLogTestContext(),
			stages: [new TimingLogTestStage()]
		);
		self::assertTrue($result->isCompleted());
	}

	public function testWritesOneRunSummaryWithoutSensitivePayloads(): void {
		$context = new TimingLogTestContext();
		$context->setVar('agent_turn_prepare_timing', [
			'total_before_orchestrator_ms' => 20.5,
			'tool_setup_ms' => 7.5,
			'prompt' => 'private preparation value'
		]);
		$entries = [];
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::once())->method('log')->willReturnCallback(
			static function(string $scope, string $message) use (&$entries): bool {
				$entries[] = [$scope, $message];
				return true;
			}
		);
		$model = $this->createStub(IAiChatModel::class);
		$orchestrator = new AgentToolOrchestrator($logger, stages: [new TimingLogTestStage()]);

		$result = $orchestrator->run($model, [], [], [], $context);

		self::assertTrue($result->isCompleted());
		self::assertCount(1, $entries);
		self::assertSame('agenttoolorchestrator', $entries[0][0]);
		self::assertStringStartsWith('[TIMING] ', $entries[0][1]);
		self::assertStringNotContainsString('private preparation value', $entries[0][1]);
		self::assertStringNotContainsString('private stage metadata', $entries[0][1]);
		self::assertStringNotContainsString('private model metadata', $entries[0][1]);
		$summary = json_decode(substr($entries[0][1], strlen('[TIMING] ')), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame(20.5, $summary['preparation_ms']['total_before_orchestrator_ms']);
		self::assertSame(7.5, $summary['preparation_ms']['tool_setup_ms']);
		self::assertGreaterThanOrEqual(0.0, $summary['orchestrator_ms']);
		self::assertEqualsWithDelta(20.5 + $summary['orchestrator_ms'], $summary['preparation_plus_orchestrator_ms'], 0.001);
		self::assertSame([31.25], $summary['model_result_durations_ms']);
		self::assertSame('timing-test-stage', $summary['stages'][0]['stage']);
		self::assertSame('completed', $summary['stages'][0]['status']);
		self::assertSame(1, $summary['stages'][0]['iteration']);
		self::assertNotContains(AgentToolLoopContextKeys::RUN_STARTED_AT, $context->listVars());
	}

	public function testRecordsFailedStageWithoutReplacingOriginalException(): void {
		$context = new TimingLogTestContext();
		$messages = [];
		$logger = $this->createStub(ILogger::class);
		$logger->method('log')->willReturnCallback(
			static function(string $scope, string $message) use (&$messages): bool {
				$messages[] = $message;
				return true;
			}
		);
		$model = $this->createStub(IAiChatModel::class);
		$orchestrator = new AgentToolOrchestrator($logger, stages: [new TimingLogTestStage(true)]);

		try {
			$orchestrator->run($model, [], [], [], $context);
			self::fail('The failing stage must propagate its exception.');
		} catch (\RuntimeException $exception) {
			self::assertSame('timing stage failure', $exception->getMessage());
		}

		self::assertCount(1, $messages);
		$summary = json_decode(substr($messages[0], strlen('[TIMING] ')), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('failed', $summary['stages'][0]['status']);
		self::assertNotContains(AgentToolLoopContextKeys::RUN_STARTED_AT, $context->listVars());
	}

	public function testLoggingFailureDoesNotAbortACompletedRun(): void {
		$context = new TimingLogTestContext();
		$logger = $this->createStub(ILogger::class);
		$logger->method('log')->willThrowException(new \RuntimeException('Logging is unavailable.'));
		$model = $this->createStub(IAiChatModel::class);
		$orchestrator = new AgentToolOrchestrator($logger, stages: [new TimingLogTestStage()]);

		$result = $orchestrator->run($model, [], [], [], $context);

		self::assertTrue($result->isCompleted());
		self::assertNotContains(AgentToolLoopContextKeys::RUN_STARTED_AT, $context->listVars());
	}

}

require_once __DIR__ . '/TimingLogTestStage.php';
require_once __DIR__ . '/TimingLogTestContext.php';
