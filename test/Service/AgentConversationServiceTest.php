<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationRequest;
use Base3\Session\Api\ISession;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Profile\AgentMemoryProfileResolver;
use MissionBay\Resource\SessionMemoryAgentResource;
use MissionBay\Service\AgentConversationService;
use PHPUnit\Framework\TestCase;

final class AgentConversationServiceTest extends TestCase {

	public function testConversationOperationsUseConfiguredMemoryProfileAndChannel(): void {
		$memory = new SessionMemoryAgentResource(
			new ConversationSessionDouble(),
			new ConversationConfigValueResolverDouble(),
			'main-memory'
		);
		$store = new ConversationSettingsStoreDouble([
			AgentMemoryProfileResolver::SETTINGS_GROUP => [
				'main' => [
					'enabled' => true,
					'memories' => ['main-memory']
				]
			]
		]);
		$repository = new ConversationPresetRepositoryDouble([
			'main-memory' => [
				'id' => 'main-memory',
				'type' => SessionMemoryAgentResource::getName(),
				'enabled' => true
			]
		]);
		$factory = new ConversationResourceFactoryDouble($memory);
		$service = new AgentConversationService(
			new AgentMemoryProfileResolver($store, $repository, $factory),
			new ConversationPresetMaterializerDouble($memory)
		);
		$request = new AgentConversationRequest(
			['memory_profile' => 'main'],
			['conversation_channel_id' => 'chatbot:one'],
			'assistant'
		);

		$state = $service->createConversation($request, 'conversation-one', 'Temporary');
		$this->assertSame('conversation-one', $state->getActiveConversation()?->getId());
		$this->assertCount(1, $state->getConversations());

		$appended = $service->appendMessage($request, 'conversation-one', [
			'id' => 'message-one',
			'role' => 'assistant',
			'content' => 'Hello from the assistant.',
			'timestamp' => '2026-07-29T18:00:00+02:00',
			'feedback' => null
		]);
		$this->assertSame('Hello from the assistant.', $appended->getMessages()[0]['content'] ?? null);

		$renamed = $service->renameConversation(
			$request,
			'conversation-one',
			'Manual title',
			AgentConversation::TITLE_SOURCE_MANUAL
		);
		$this->assertSame('Manual title', $renamed->getActiveConversation()?->getTitle());

		$deleted = $service->deleteConversation($request, 'conversation-one');
		$this->assertSame([], $deleted->getConversations());
		$this->assertNull($deleted->getActiveConversation());
	}
}

require_once __DIR__ . '/ConversationPresetMaterializerDouble.php';
require_once __DIR__ . '/ConversationSessionDouble.php';
require_once __DIR__ . '/ConversationConfigValueResolverDouble.php';
require_once __DIR__ . '/ConversationSettingsStoreDouble.php';
require_once __DIR__ . '/ConversationPresetRepositoryDouble.php';
require_once __DIR__ . '/ConversationResourceFactoryDouble.php';
