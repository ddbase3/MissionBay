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

namespace Base3\MissionBay\Test\Service\Assistant;

use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\MissionBay\Api\IAgentAssistantToolSetupFactory;
use Base3\MissionBay\Capability\AgentCapabilityDiscoveryService;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentStagePipelineResolver;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Suspension\UnavailableAgentSuspensionRepository;
use Base3\MissionBay\Service\Assistant\AgentAssistantContextContributionService;
use Base3\MissionBay\Service\Assistant\AgentAssistantFallbackBuilder;
use Base3\MissionBay\Service\Assistant\AgentAssistantFinalResponseService;
use Base3\MissionBay\Service\Assistant\AgentAssistantMemoryService;
use Base3\MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use Base3\MissionBay\Service\Assistant\AgentAssistantTurnService;
use Base3\MissionBay\Service\Memory\AgentMemoryRoleResolver;
use Base3\MissionBay\Test\Orchestrator\LookupTool;
use Base3\MissionBay\Test\Orchestrator\ModelDecisionQueueChatModel;
use Base3\MissionBay\Test\Orchestrator\RecordingAgentStageComponentResolver;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentMemorySeparationTestDouble1.php';
require_once __DIR__ . '/AgentMemorySeparationTestDouble4.php';
require_once dirname(__DIR__, 2) . '/Orchestrator/LookupTool.php';
require_once dirname(__DIR__, 2) . '/Orchestrator/ModelDecisionQueueChatModel.php';
require_once dirname(__DIR__, 2) . '/Orchestrator/RecordingAgentStageComponentResolver.php';

final class DirectCallAgentTurnTest extends TestCase {

	public function testBufferedAndStreamingTurnsRetainHistoryAndContextWithoutTools(): void {
		foreach ([false, true] as $streaming) {
			$model = new ModelDecisionQueueChatModel([new AiChatResult('Answer', [], new AiResultMetadata('chat', 'test'))]);
			$memory = new AgentMemorySeparationTestDouble1();
			$resources = new AgentAssistantTurnResources($model, [$memory], [new LookupTool()], contextContributors: [new AgentMemorySeparationTestDouble4()]);
			$messageFactory = new AgentAssistantMessageFactory();
			$memoryService = new AgentAssistantMemoryService($messageFactory, new AgentMemoryRoleResolver());
			$resolver = new RecordingAgentStageComponentResolver([]);
			$service = $this->turnService($memoryService, $messageFactory, $resolver);
			$turn = $service->run($resources, new AgentContext(), new AgentAssistantTurnOptions(
				prompt: 'Current request', system: 'Agent instructions', maxToolLoops: 0,
				toolsEnabled: true, nodeId: 'assistant', assistantMessageId: 'answer-id',
				stageIds: ['model-decision'], deliberatePlanningEnabled: true
			));
			$this->assertSame(1, $memory->loads);
			$this->assertSame(1, $memory->writes);
			$this->assertSame([], $resolver->getRequestedIds());
			$this->assertNull($turn->getOrchestrationResult());
			$this->assertSame(0, $turn->getIterations());
			$this->assertSame([], $turn->getToolCalls());
			$finalService = new AgentAssistantFinalResponseService($messageFactory);
			$chunks = [];
			$content = $streaming
				? $finalService->createStreamingResponse($model, $turn, static function(string $delta) use (&$chunks): void { $chunks[] = $delta; })
				: $finalService->createDirectResponse($model, $turn);
			$this->assertSame('Answer', $content);
			$this->assertSame($streaming ? ['Answer'] : [], $chunks);
			$this->assertSame($streaming ? 0 : 1, $model->getCompleteCalls());
			$this->assertSame($streaming ? 1 : 0, $model->getStreamCalls());
			$this->assertSame([], $model->getLastTools());
			$messages = $model->getLastMessages();
			$this->assertStringContainsString('Agent instructions', $messages[0]['content']);
			$this->assertContains('Pure context block', array_column($messages, 'content'));
			$this->assertContains('Stored conversation', array_column($messages, 'content'));
			$this->assertSame('Current request', $messages[count($messages) - 1]['content']);
			$this->assertCount(1, $turn->getModelResults());
			$memoryService->appendVisibleMessage($turn->getMemories(), $turn->getNodeId(), $finalService->createAssistantMessage($turn, $content));
			$this->assertSame(2, $memory->writes);
		}
	}

	public function testZeroLoopTurnCannotResumeToolSuspension(): void {
		$model = new ModelDecisionQueueChatModel([]);
		$messageFactory = new AgentAssistantMessageFactory();
		$memoryService = new AgentAssistantMemoryService($messageFactory, new AgentMemoryRoleResolver());
		$service = $this->turnService($memoryService, $messageFactory, new RecordingAgentStageComponentResolver([]));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('tool orchestration disabled');
		$service->run(new AgentAssistantTurnResources($model), new AgentContext(), new AgentAssistantTurnOptions(
			prompt: '', maxToolLoops: 0, resume: new AgentResume('opaque-handle')
		));
	}

	private function turnService(AgentAssistantMemoryService $memoryService, AgentAssistantMessageFactory $messageFactory, RecordingAgentStageComponentResolver $resolver): AgentAssistantTurnService {
		$toolSetup = $this->createMock(IAgentAssistantToolSetupFactory::class);
		$toolSetup->expects($this->never())->method('create');
		$orchestrator = $this->createMock(AgentToolOrchestrator::class);
		$orchestrator->expects($this->never())->method('run');
		return new AgentAssistantTurnService(
			$memoryService,
			new AgentAssistantContextContributionService(new AgentMemoryRoleResolver()),
			$messageFactory,
			$toolSetup,
			new AgentCapabilityDiscoveryService($resolver),
			new AgentStagePipelineResolver($resolver, ['model-decision', 'action-policy', 'tool-execution', 'tool-observation']),
			$orchestrator,
			new AgentActionResumeService(new AgentActionFingerprint(), new UnavailableAgentSuspensionRepository()),
			new AgentAssistantFallbackBuilder()
		);
	}
}
