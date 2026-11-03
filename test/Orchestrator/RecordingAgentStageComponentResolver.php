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
