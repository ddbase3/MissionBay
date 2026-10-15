<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentStageResult;
use Base3\Logger\Api\ILogger;
use Base3\Api\IContainer;
use MissionBay\MissionBayPlugin;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\AgentStateSynchronizer;
use MissionBay\Orchestrator\Service\AgentActionResumeService;
use MissionBay\Orchestrator\Service\AgentBudgetGuardService;
use MissionBay\Orchestrator\Service\AgentLoopProgressService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Suspension\UnavailableAgentSuspensionRepository;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentToolOrchestratorTimingLogTest extends TestCase {

	public function testPluginCompositionUsesRegisteredLoggerWithoutPerRunLogger(): void {
		$services = [];
		$container = $this->createMock(IContainer::class);
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
			$this->createMock(IAiChatModel::class),
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
		$model = $this->createMock(IAiChatModel::class);
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
		$logger = $this->createMock(ILogger::class);
		$logger->method('log')->willReturnCallback(
			static function(string $scope, string $message) use (&$messages): bool {
				$messages[] = $message;
				return true;
			}
		);
		$model = $this->createMock(IAiChatModel::class);
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
		$logger = $this->createMock(ILogger::class);
		$logger->method('log')->willThrowException(new \RuntimeException('Logging is unavailable.'));
		$model = $this->createMock(IAiChatModel::class);
		$orchestrator = new AgentToolOrchestrator($logger, stages: [new TimingLogTestStage()]);

		$result = $orchestrator->run($model, [], [], [], $context);

		self::assertTrue($result->isCompleted());
		self::assertNotContains(AgentToolLoopContextKeys::RUN_STARTED_AT, $context->listVars());
	}

}

final class TimingLogTestStage implements IAgentStage {

	public function __construct(private bool $fails = false) {}

	public static function getName(): string {
		return 'timinglogteststage';
	}

	public function id(): string {
		return 'timing-test-stage';
	}

	public function name(): string {
		return 'timing-test-stage';
	}

	public function getDescription(): string {
		return 'Stage for timing log regression tests.';
	}

	public function getAiUsage(): string {
		return self::AI_USAGE_CONDITIONAL;
	}

	public function supports(IAgentContext $context): bool {
		return true;
	}

	public function process(IAgentContext $context): AgentStageResult {
		if ($this->fails) {
			throw new \RuntimeException('timing stage failure');
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FINAL,
			AgentToolLoopContextKeys::COMPLETED => true,
			AgentToolLoopContextKeys::MODEL_RESULTS => [[
				'duration_ms' => 31.25,
				'extra' => ['secret' => 'private model metadata']
			]]
		], ['secret' => 'private stage metadata']);
	}
}

final class TimingLogTestContext implements IAgentContext {

	private array $values = [];

	public static function getName(): string {
		return 'timinglogtestcontext';
	}

	public function getMemory(): IAgentMemory {
		throw new \LogicException('Memory is not used by this test.');
	}

	public function setMemory(IAgentMemory $memory): void {
		throw new \LogicException('Memory is not used by this test.');
	}

	public function setVar(string $key, mixed $value): void {
		$this->values[$key] = $value;
	}

	public function getVar(string $key): mixed {
		return $this->values[$key] ?? null;
	}

	public function forgetVar(string $key): void {
		unset($this->values[$key]);
	}

	public function listVars(): array {
		return array_keys($this->values);
	}
}
