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

final class ConversationPresetMaterializerDouble implements IAgentComponentPresetMaterializer {

	public function __construct(private readonly SessionMemoryAgentResource $memory) {}

	public function createContext(array $vars = []): IAgentContext {
		return new AgentContext(null, $vars);
	}

	public function materialize(string $presetId, IAgentContext $context): AgentComponentPresetMaterialization {
		$this->memory->init([], $context);
		return new AgentComponentPresetMaterialization(
			$presetId,
			['id' => $presetId, 'type' => SessionMemoryAgentResource::getName()],
			$this->memory,
			null,
			$this->memory,
			null,
			['memory']
		);
	}
}
