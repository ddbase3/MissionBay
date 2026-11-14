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
use Base3\AssistantFoundation\Dto\AgentConversation;
use Base3\AssistantFoundation\Dto\AgentConversationRequest;
use Base3\CoreFoundation\Session\Api\ISession;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\AgentComponentPresetMaterialization;
use Base3\MissionBay\Profile\AgentMemoryProfileResolver;
use Base3\MissionBay\Resource\SessionMemoryAgentResource;
use Base3\MissionBay\Service\AgentConversationService;
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
