<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentTextTaskRequest;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentAssistantContextContributionService;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentFlowCompiler;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Dto\AgentFlowCompilation;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentToolProfileResolver;
use MissionBay\Service\AgentTextTaskService;
use PHPUnit\Framework\TestCase;

final class TextTaskFlowDouble implements IAgentFlow {

	public function __construct(
		private array $resources,
		private ?IAgentContext $context = null
	) {}

	public static function getName(): string { return 'texttaskflowdouble'; }
	public function setContext(IAgentContext $context): void { $this->context = $context; }
	public function run(array $inputs): array { return []; }
	public function addNode(IAgentNode $node): void {}
	public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput): void {}
	public function addInitialInput(string $nodeId, string $key, mixed $value): void {}
	public function getInitialInputs(): array { return []; }
	public function getConnections(): array { return []; }
	public function getNextNode(string $currentNodeId, array $output): ?string { return null; }
	public function mapInputs(string $fromNodeId, string $toNodeId, array $output): array { return []; }
	public function isReady(string $nodeId, array $currentInputs): bool { return false; }
	public function addResource(IAgentResource $resource): void { $this->resources[$resource->getId()] = $resource; }
	public function getResources(): array { return $this->resources; }
	public function addDockConnection(string $nodeId, string $dockName, string $resourceId): void {}
	public function getAllDockConnections(): array { return []; }
	public function getDockConnections(string $nodeId): array { return []; }
}
