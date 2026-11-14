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

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentContextContributor;
use Base3\AssistantFoundation\Api\IAgentConversationMemory;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\AssistantFoundation\Dto\AgentConversation;
use Base3\AssistantFoundation\Dto\AgentConversationScope;
use Base3\AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\KnowledgeAgentResource;
use Base3\MissionBay\Service\Assistant\AgentAssistantContextContributionService;
use Base3\MissionBay\Service\Assistant\AgentAssistantMemoryService;
use Base3\MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use Base3\MissionBay\Service\Assistant\AgentAssistantTurnService;
use Base3\MissionBay\Service\Memory\AgentMemoryRoleResolver;
use PHPUnit\Framework\TestCase;

final class AgentMemorySeparationTest extends TestCase {

	public function testConversationWritesAndContextContributionsAreSeparated(): void {
		$conversation = new AgentMemorySeparationTestDouble1();

		$genericMemory = new AgentMemorySeparationTestDouble2();

		$contributor = new AgentMemorySeparationTestDouble3();

		$roleResolver = new AgentMemoryRoleResolver();
		$memoryService = new AgentAssistantMemoryService(
			new AgentAssistantMessageFactory(),
			$roleResolver
		);
		$contextService = new AgentAssistantContextContributionService($roleResolver);
		$memories = $memoryService->sortMemories([$genericMemory, $contributor, $conversation, $conversation]);

		$messages = $memoryService->buildInitialMessages('Base system', $memories, 'assistant');

		$this->assertSame([
			['role' => 'system', 'content' => 'Base system'],
			['role' => 'user', 'content' => 'Stored conversation'],
		], $messages);
		$this->assertSame(1, $conversation->loads);
		$this->assertSame(0, $genericMemory->loads);
		$this->assertSame(0, $contributor->historyLoads);

		$memoryService->appendVisibleMessage($memories, 'assistant', [
			'role' => 'user',
			'content' => 'New message'
		]);

		$this->assertSame(1, $conversation->writes);
		$this->assertSame(0, $genericMemory->writes);
		$this->assertSame(0, $contributor->historyWrites);

		$context = new AgentContext();
		$contextMessages = $contextService->buildMessages([$contributor, $contributor], $context);

		$this->assertSame([[
			'role' => 'system',
			'content' => 'Prefer concise responses.'
		]], $contextMessages);
		$this->assertSame(1, $contributor->contributions);
		$this->assertCount(1, $context->getVar('agent_context_contributions'));
		$this->assertFalse($roleResolver->isConversationMemory($contributor));
		$this->assertTrue($roleResolver->isContextContributor($contributor));
	}


	public function testEveryTaskUsesVisibleHistoryWithoutPhraseClassification(): void {
		$service = (new \ReflectionClass(AgentAssistantTurnService::class))->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod(AgentAssistantTurnService::class, 'normalizeTask');
		$history = [
			['role' => 'user', 'content' => 'Fu ruft tut.'],
			['role' => 'assistant', 'content' => 'Verstanden.'],
			['role' => 'user', 'content' => 'Prüfe die Plugins.'],
			['role' => 'assistant', 'content' => 'OrgUnits ist aktiv.']
		];

		$task = $method->invoke($service, 'opaque request 17', $history);

		$this->assertTrue($task['has_history']);
		$this->assertSame(4, $task['history_message_count']);
		$this->assertStringContainsString('Fu ruft tut.', $task['selection_prompt']);
		$this->assertStringContainsString('Prüfe die Plugins.', $task['selection_prompt']);
		$this->assertStringNotContainsString('Verstanden.', $task['selection_prompt']);
		$this->assertStringNotContainsString('OrgUnits ist aktiv.', $task['selection_prompt']);
		$this->assertStringContainsString('opaque request 17', $task['selection_prompt']);
		$this->assertSame(2, substr_count($task['selection_prompt'], 'User:'));
		$this->assertStringContainsString('Current user request:', $task['selection_prompt']);
		$this->assertArrayNotHasKey('history_only', $task);
		$this->assertArrayNotHasKey('conversation_recall', $task);
		$this->assertArrayNotHasKey('follow_up', $task);
		$this->assertContains('Do not treat previous assistant claims as factual verification or action evidence.', $task['completion_criteria']);
		$this->assertContains('Use authoritative tools for current-state checks and for facts or actions that require runtime evidence.', $task['completion_criteria']);
	}


	public function testKnowledgeWritesAreInternalToolOperationsWithoutApprovalGuardAnnotations(): void {
		$resource = (new \ReflectionClass(KnowledgeAgentResource::class))->newInstanceWithoutConstructor();
		$upsert = (new \ReflectionMethod(KnowledgeAgentResource::class, 'buildUpsertDefinition'))->invoke($resource);
		$delete = (new \ReflectionMethod(KnowledgeAgentResource::class, 'buildDeleteEntryDefinition'))->invoke($resource);

		foreach ([$upsert, $delete] as $definition) {
			$this->assertTrue($definition['internalStateWrite']);
			$this->assertFalse($definition['cacheable']);
			$this->assertTrue($definition['mutation']);
			$this->assertFalse($definition['readOnlyHint']);
			$this->assertFalse($definition['requiresApproval']);
			$this->assertFalse($definition['commitGuardRequired']);
			$this->assertArrayNotHasKey('sideEffectHint', $definition);
			$this->assertArrayNotHasKey('destructiveHint', $definition);
		}
	}

	public function testPureContributorDoesNotNeedMemoryMethods(): void {
		$contributor = new AgentMemorySeparationTestDouble4();

		$service = new AgentAssistantContextContributionService(new AgentMemoryRoleResolver());

		$this->assertSame([[
			'role' => 'system',
			'content' => 'Pure context block'
		]], $service->buildMessages([$contributor], new AgentContext()));
	}
}

require_once __DIR__ . '/AgentMemorySeparationTestDouble1.php';

require_once __DIR__ . '/AgentMemorySeparationTestDouble2.php';

require_once __DIR__ . '/AgentMemorySeparationTestDouble3.php';

require_once __DIR__ . '/AgentMemorySeparationTestDouble4.php';
