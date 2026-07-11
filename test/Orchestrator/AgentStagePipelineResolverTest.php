<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentStageResult;
use Base3\Api\IComponent;
use Base3\Api\IComponentResolver;
use MissionBay\Orchestrator\AgentStagePipelineResolver;
use PHPUnit\Framework\TestCase;

final class AgentStagePipelineResolverTest extends TestCase {

	private const DEFAULT_STAGE_IDS = [
		'model-decision',
		'action-policy',
		'tool-execution',
		'context-compaction',
		'tool-observation',
		'semantic-verification'
	];

	public function testEmptyConfigurationResolvesDefaultPipelineInOrder(): void {
		$components = new RecordingAgentStageComponentResolver([
			'model-decision' => new RecordingResolvedAgentStage('model-decision'),
			'action-policy' => new RecordingResolvedAgentStage('action-policy'),
			'tool-execution' => new RecordingResolvedAgentStage('tool-execution'),
			'context-compaction' => new RecordingResolvedAgentStage('context-compaction'),
			'tool-observation' => new RecordingResolvedAgentStage('tool-observation'),
			'semantic-verification' => new RecordingResolvedAgentStage('semantic-verification')
		]);
		$resolver = new AgentStagePipelineResolver($components, self::DEFAULT_STAGE_IDS);

		$stages = $resolver->resolve();

		$this->assertSame(self::DEFAULT_STAGE_IDS, array_map(fn(IAgentStage $stage) => $stage->id(), $stages));
		$this->assertSame(self::DEFAULT_STAGE_IDS, $components->getRequestedIds());
	}

	public function testConfiguredPipelineResolvesStagesInExplicitOrder(): void {
		$components = new RecordingAgentStageComponentResolver([
			'budget-guard' => new RecordingResolvedAgentStage('budget-guard'),
			'model-decision' => new RecordingResolvedAgentStage('model-decision'),
			'context-assessment' => new RecordingResolvedAgentStage('context-assessment'),
			'tool-execution' => new RecordingResolvedAgentStage('tool-execution'),
			'tool-observation' => new RecordingResolvedAgentStage('tool-observation'),
			'final-budget-guard' => new RecordingResolvedAgentStage('final-budget-guard')
		]);
		$resolver = new AgentStagePipelineResolver($components, self::DEFAULT_STAGE_IDS);

		$stages = $resolver->resolve([
			'model-decision',
			'tool-execution',
			'context-assessment',
			'tool-observation'
		]);

		$this->assertSame([
			'model-decision',
			'tool-execution',
			'context-assessment',
			'tool-observation'
		], array_map(fn(IAgentStage $stage) => $stage->id(), $stages));
	}

	public function testExplicitPipelineCanSelectSemanticVerification(): void {
		$components = new RecordingAgentStageComponentResolver([
			'result-verification' => new RecordingResolvedAgentStage('result-verification'),
			'semantic-verification' => new RecordingResolvedAgentStage('semantic-verification'),
			'tool-observation' => new RecordingResolvedAgentStage('tool-observation'),
			'final-budget-guard' => new RecordingResolvedAgentStage('final-budget-guard')
		]);
		$resolver = new AgentStagePipelineResolver($components, self::DEFAULT_STAGE_IDS);

		$stages = $resolver->resolve([
			'result-verification',
			'semantic-verification',
			'tool-observation'
		]);

		$this->assertSame([
			'result-verification',
			'semantic-verification',
			'tool-observation'
		], array_map(fn(IAgentStage $stage) => $stage->id(), $stages));
	}

	public function testMissingConfiguredStageIsRejected(): void {
		$resolver = new AgentStagePipelineResolver(
			new RecordingAgentStageComponentResolver([]),
			self::DEFAULT_STAGE_IDS
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Configured agent stage could not be resolved: missing-stage');

		$resolver->resolve(['missing-stage']);
	}

	public function testDuplicateConfiguredStageIdsAreRejected(): void {
		$resolver = new AgentStagePipelineResolver(
			new RecordingAgentStageComponentResolver([
				'model-decision' => new RecordingResolvedAgentStage('model-decision')
			]),
			self::DEFAULT_STAGE_IDS
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Duplicate agent stage id: model-decision');

		$resolver->resolve(['model-decision', 'model-decision']);
	}
}

final class RecordingAgentStageComponentResolver implements IComponentResolver {

	/**
	 * @var array<string,IAgentStage>
	 */
	private array $stages;

	/**
	 * @var array<int,string>
	 */
	private array $requestedIds = [];

	/**
	 * @param array<string,IAgentStage> $stages
	 */
	public function __construct(array $stages) {
		$this->stages = $stages;
	}

	public function has(string $interfaceName, string $id): bool {
		return $interfaceName === IAgentStage::class && isset($this->stages[$id]);
	}

	public function get(string $interfaceName, string $id): ?IComponent {
		$this->requestedIds[] = $id;

		if ($interfaceName !== IAgentStage::class) {
			return null;
		}

		return $this->stages[$id] ?? null;
	}

	public function all(string $interfaceName): iterable {
		if ($interfaceName !== IAgentStage::class) {
			return [];
		}

		return array_values($this->stages);
	}

	/**
	 * @return array<int,string>
	 */
	public function getRequestedIds(): array {
		return $this->requestedIds;
	}
}

final class RecordingResolvedAgentStage implements IAgentStage {

	public function __construct(private readonly string $id) {}

	public static function getName(): string {
		return 'recordingresolvedagentstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->id;
	}

	public function getDescription(): string {
		return 'Test stage.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		return false;
	}

	public function process(IAgentContext $context): AgentStageResult {
		return AgentStageResult::none();
	}
}
