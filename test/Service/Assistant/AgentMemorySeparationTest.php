<?php declare(strict_types=1);

namespace MissionBay\Test\Service\Assistant;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Dto\AgentInstructionBlock;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\KnowledgeAgentResource;
use MissionBay\Service\Assistant\AgentAssistantContextContributionService;
use MissionBay\Service\Assistant\AgentAssistantMemoryService;
use MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use MissionBay\Service\Assistant\AgentAssistantTurnService;
use MissionBay\Service\Memory\AgentMemoryRoleResolver;
use PHPUnit\Framework\TestCase;

final class AgentMemorySeparationTest extends TestCase {

	public function testConversationWritesAndContextContributionsAreSeparated(): void {
		$conversation = new class implements IAgentConversationMemory {
			public int $loads = 0;
			public int $writes = 0;

			public static function getName(): string {
				return 'testconversationmemory';
			}

			public function loadNodeHistory(string $nodeId): array {
				$this->loads++;
				return [[
					'role' => 'user',
					'content' => 'Stored conversation'
				]];
			}

			public function appendNodeHistory(string $nodeId, array $message): void {
				$this->writes++;
			}

			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
				return false;
			}

			public function resetNodeHistory(string $nodeId): void {
			}

			public function getPriority(): int {
				return 20;
			}
		};

		$legacy = new class implements IAgentMemory {
			public int $loads = 0;
			public int $writes = 0;

			public static function getName(): string {
				return 'testlegacymemory';
			}

			public function loadNodeHistory(string $nodeId): array {
				$this->loads++;
				return [[
					'role' => 'assistant',
					'content' => 'Legacy conversation'
				]];
			}

			public function appendNodeHistory(string $nodeId, array $message): void {
				$this->writes++;
			}

			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
				return false;
			}

			public function resetNodeHistory(string $nodeId): void {
			}

			public function getPriority(): int {
				return 30;
			}
		};

		$contributor = new class implements IAgentMemory, IAgentContextContributor {
			public int $legacyLoads = 0;
			public int $legacyWrites = 0;
			public int $contributions = 0;

			public static function getName(): string {
				return 'testcontextcontributor';
			}

			public function id(): string {
				return 'context-primary';
			}

			public function contribute(IAgentContext $context): iterable {
				$this->contributions++;
				return [new AgentInstructionBlock(
					id: 'preferences',
					content: 'Prefer concise responses.',
					priority: 5,
					source: $this->id()
				)];
			}

			public function loadNodeHistory(string $nodeId): array {
				$this->legacyLoads++;
				return [[
					'role' => 'system',
					'content' => 'Legacy contributor path must not be used.'
				]];
			}

			public function appendNodeHistory(string $nodeId, array $message): void {
				$this->legacyWrites++;
			}

			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
				return false;
			}

			public function resetNodeHistory(string $nodeId): void {
			}

			public function getPriority(): int {
				return 10;
			}
		};

		$roleResolver = new AgentMemoryRoleResolver();
		$memoryService = new AgentAssistantMemoryService(
			new AgentAssistantMessageFactory(),
			$roleResolver
		);
		$contextService = new AgentAssistantContextContributionService($roleResolver);
		$memories = $memoryService->sortMemories([$legacy, $contributor, $conversation, $conversation]);

		$messages = $memoryService->buildInitialMessages('Base system', $memories, 'assistant');

		$this->assertSame([
			['role' => 'system', 'content' => 'Base system'],
			['role' => 'user', 'content' => 'Stored conversation'],
			['role' => 'assistant', 'content' => 'Legacy conversation']
		], $messages);
		$this->assertSame(1, $conversation->loads);
		$this->assertSame(1, $legacy->loads);
		$this->assertSame(0, $contributor->legacyLoads);

		$memoryService->appendVisibleMessage($memories, 'assistant', [
			'role' => 'user',
			'content' => 'New message'
		]);

		$this->assertSame(1, $conversation->writes);
		$this->assertSame(1, $legacy->writes);
		$this->assertSame(0, $contributor->legacyWrites);

		$context = new AgentContext();
		$contextMessages = $contextService->buildMessages([$contributor, $contributor], $context);

		$this->assertSame([[
			'role' => 'system',
			'content' => 'Prefer concise responses.'
		]], $contextMessages);
		$this->assertSame(1, $contributor->contributions);
		$this->assertCount(1, $context->getVar('agent_context_contributions'));
		$this->assertTrue($roleResolver->isLegacyMemory($legacy));
		$this->assertFalse($roleResolver->isLegacyMemory($conversation));
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
		$this->assertStringContainsString('opaque request 17', $task['selection_prompt']);
		$this->assertArrayNotHasKey('history_only', $task);
		$this->assertArrayNotHasKey('conversation_recall', $task);
		$this->assertArrayNotHasKey('follow_up', $task);
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

	public function testPureContributorDoesNotNeedLegacyMemoryMethods(): void {
		$contributor = new class implements IAgentContextContributor {
			public static function getName(): string {
				return 'testpurecontributor';
			}

			public function id(): string {
				return 'pure-context';
			}

			public function contribute(IAgentContext $context): iterable {
				return [new AgentInstructionBlock('pure', 'Pure context block')];
			}

			public function getPriority(): int {
				return 5;
			}
		};

		$service = new AgentAssistantContextContributionService(new AgentMemoryRoleResolver());

		$this->assertSame([[
			'role' => 'system',
			'content' => 'Pure context block'
		]], $service->buildMessages([$contributor], new AgentContext()));
	}
}
