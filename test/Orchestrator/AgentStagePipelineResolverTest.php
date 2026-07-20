<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentStageMount;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentStageSlot;
use Base3\Api\IComponent;
use Base3\Api\IComponentResolver;
use MissionBay\Orchestrator\AgentStagePipelineResolver;
use PHPUnit\Framework\TestCase;

final class AgentStagePipelineResolverTest extends TestCase {

	private const DEFAULT_STAGE_IDS = [
		'capability-discovery',
		'capability-selection',
		'model-decision',
		'action-policy',
		'tool-execution',
		'context-compaction',
		'tool-observation',
		'semantic-verification'
	];

	public function testEmptyConfigurationResolvesDefaultPipelineInOrder(): void {
		$components = new RecordingAgentStageComponentResolver([
			'capability-discovery' => new RecordingResolvedAgentStage('capability-discovery'),
			'capability-selection' => new RecordingResolvedAgentStage('capability-selection'),
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

	public function testConfiguredPipelineAcceptsCanonicalOptionalSubset(): void {
		$components = new RecordingAgentStageComponentResolver([
			'capability-selection' => new RecordingResolvedAgentStage('capability-selection'),
			'model-decision' => new RecordingResolvedAgentStage('model-decision'),
			'action-policy' => new RecordingResolvedAgentStage('action-policy'),
			'tool-execution' => new RecordingResolvedAgentStage('tool-execution'),
			'tool-observation' => new RecordingResolvedAgentStage('tool-observation')
		]);
		$resolver = new AgentStagePipelineResolver($components, self::DEFAULT_STAGE_IDS);

		$stages = $resolver->resolve([
			'capability-selection',
			'model-decision',
			'action-policy',
			'tool-execution',
			'tool-observation'
		]);

		$this->assertSame([
			'capability-selection',
			'model-decision',
			'action-policy',
			'tool-execution',
			'tool-observation'
		], array_map(fn(IAgentStage $stage) => $stage->id(), $stages));
	}

	public function testConfiguredPipelineAcceptsAiCapabilitySelectionAsAlternative(): void {
		$components = new RecordingAgentStageComponentResolver([
			'capability-discovery' => new RecordingResolvedAgentStage('capability-discovery'),
			'ai-capability-selection' => new RecordingResolvedAgentStage('ai-capability-selection'),
			'model-decision' => new RecordingResolvedAgentStage('model-decision'),
			'action-policy' => new RecordingResolvedAgentStage('action-policy'),
			'tool-execution' => new RecordingResolvedAgentStage('tool-execution'),
			'tool-observation' => new RecordingResolvedAgentStage('tool-observation')
		]);
		$resolver = new AgentStagePipelineResolver($components, self::DEFAULT_STAGE_IDS);

		$stages = $resolver->resolve([
			'capability-discovery',
			'ai-capability-selection',
			'model-decision',
			'action-policy',
			'tool-execution',
			'tool-observation'
		]);

		$this->assertSame([
			'capability-discovery',
			'ai-capability-selection',
			'model-decision',
			'action-policy',
			'tool-execution',
			'tool-observation'
		], array_map(fn(IAgentStage $stage) => $stage->id(), $stages));
	}

	public function testDeterministicAndAiCapabilitySelectionCannotBeCombined(): void {
		$resolver = new AgentStagePipelineResolver(
			new RecordingAgentStageComponentResolver([]),
			self::DEFAULT_STAGE_IDS
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Capability selection stages are mutually exclusive');

		$resolver->resolve([
			'capability-selection',
			'ai-capability-selection',
			'model-decision',
			'action-policy',
			'tool-execution',
			'tool-observation'
		]);
	}

	public function testFreeReorderingIsRejected(): void {
		$resolver = new AgentStagePipelineResolver(
			new RecordingAgentStageComponentResolver([]),
			self::DEFAULT_STAGE_IDS
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid agent stage order near: action-policy');

		$resolver->resolve([
			'model-decision',
			'tool-execution',
			'action-policy',
			'tool-observation'
		]);
	}

	public function testExplicitPipelineCanSelectSemanticVerification(): void {
		$components = new RecordingAgentStageComponentResolver([
			'model-decision' => new RecordingResolvedAgentStage('model-decision'),
			'action-policy' => new RecordingResolvedAgentStage('action-policy'),
			'tool-execution' => new RecordingResolvedAgentStage('tool-execution'),
			'tool-observation' => new RecordingResolvedAgentStage('tool-observation'),
			'semantic-verification' => new RecordingResolvedAgentStage('semantic-verification')
		]);
		$resolver = new AgentStagePipelineResolver($components, self::DEFAULT_STAGE_IDS);

		$stages = $resolver->resolve([
			'model-decision',
			'action-policy',
			'tool-execution',
			'tool-observation',
			'semantic-verification'
		]);

		$this->assertSame([
			'model-decision',
			'action-policy',
			'tool-execution',
			'tool-observation',
			'semantic-verification'
		], array_map(fn(IAgentStage $stage) => $stage->id(), $stages));
	}


	public function testRunLocalModuleStagesAreMountedIntoSemanticSlots(): void {
		$components = new RecordingAgentStageComponentResolver([
			'capability-discovery' => new RecordingResolvedAgentStage('capability-discovery'),
			'capability-selection' => new RecordingResolvedAgentStage('capability-selection'),
			'model-decision' => new RecordingResolvedAgentStage('model-decision'),
			'action-policy' => new RecordingResolvedAgentStage('action-policy'),
			'tool-execution' => new RecordingResolvedAgentStage('tool-execution'),
			'context-compaction' => new RecordingResolvedAgentStage('context-compaction'),
			'tool-observation' => new RecordingResolvedAgentStage('tool-observation'),
			'semantic-verification' => new RecordingResolvedAgentStage('semantic-verification')
		]);
		$resolver = new AgentStagePipelineResolver($components, self::DEFAULT_STAGE_IDS);

		$stages = $resolver->resolve([], [
			new AgentStageMount(AgentStageSlot::BEFORE_TOOL_CALL, new RecordingResolvedAgentStage('module-before-tool'), 20),
			new AgentStageMount(AgentStageSlot::BEFORE_TOOL_CALL, new RecordingResolvedAgentStage('module-before-tool-first'), 10),
			new AgentStageMount(AgentStageSlot::AFTER_TOOL_CALL, new RecordingResolvedAgentStage('module-after-tool')),
			new AgentStageMount(AgentStageSlot::BEFORE_FINAL_ANSWER, new RecordingResolvedAgentStage('module-before-final'))
		]);

		$this->assertSame([
			'capability-discovery',
			'capability-selection',
			'model-decision',
			'action-policy',
			'module-before-tool-first',
			'module-before-tool',
			'tool-execution',
			'module-after-tool',
			'context-compaction',
			'tool-observation',
			'module-before-final',
			'semantic-verification'
		], array_map(fn(IAgentStage $stage) => $stage->id(), $stages));
	}

	public function testMountWithoutSelectedPipelineAnchorIsRejected(): void {
		$resolver = new AgentStagePipelineResolver(
			new RecordingAgentStageComponentResolver([
				'model-decision' => new RecordingResolvedAgentStage('model-decision'),
				'action-policy' => new RecordingResolvedAgentStage('action-policy'),
				'tool-execution' => new RecordingResolvedAgentStage('tool-execution'),
				'tool-observation' => new RecordingResolvedAgentStage('tool-observation')
			]),
			self::DEFAULT_STAGE_IDS
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('no anchor for slot: before_final_answer');

		$resolver->resolve(['model-decision', 'action-policy', 'tool-execution', 'tool-observation'], [
			new AgentStageMount(AgentStageSlot::BEFORE_FINAL_ANSWER, new RecordingResolvedAgentStage('module-before-final'))
		]);
	}

	public function testMissingConfiguredStageIsRejected(): void {
		$resolver = new AgentStagePipelineResolver(
			new RecordingAgentStageComponentResolver([]),
			self::DEFAULT_STAGE_IDS
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Configured agent stage could not be resolved: action-policy');

		$resolver->resolve(['model-decision', 'action-policy', 'tool-execution', 'tool-observation']);
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
