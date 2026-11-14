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

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentTextTaskRequest;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentAssistantContextContributionService;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentFlow;
use Base3\MissionBay\Api\IAgentFlowCompiler;
use Base3\MissionBay\Api\IAgentFlowFactory;
use Base3\MissionBay\Api\IAgentNode;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\AgentComponentPresetMaterialization;
use Base3\MissionBay\Dto\AgentFlowCompilation;
use Base3\MissionBay\Profile\AgentContextProfileResolver;
use Base3\MissionBay\Profile\AgentToolProfileResolver;
use Base3\MissionBay\Service\AgentTextTaskService;
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
