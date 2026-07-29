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

final class ConversationSessionDouble implements ISession {
	private array $values = [];
	private bool $started = false;
	public function started(): bool { return $this->started; }
	public function getId(): string { return 'session-one'; }
	public function start(): bool { $this->started = true; return true; }
	public function destroy(): bool { $this->values = []; $this->started = false; return true; }
	public function get(string $key, mixed $default = null): mixed { return $this->values[$key] ?? $default; }
	public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
	public function has(string $key): bool { return array_key_exists($key, $this->values); }
	public function remove(string $key): void { unset($this->values[$key]); }
}

final class ConversationConfigValueResolverDouble implements IAgentConfigValueResolver {
	public function resolveValue(array|string|int|float|bool|null $config): mixed { return $config; }
}

final class ConversationSettingsStoreDouble implements ISettingsStore {
	public function __construct(private array $groups) {}
	public function get(string $group, string $name, array $default = []): array { return $this->groups[$group][$name] ?? $default; }
	public function set(string $group, string $name, array $settings): void { $this->groups[$group][$name] = $settings; }
	public function has(string $group, string $name): bool { return isset($this->groups[$group][$name]); }
	public function remove(string $group, string $name): void { unset($this->groups[$group][$name]); }
	public function getGroup(string $group): array { return $this->groups[$group] ?? []; }
	public function save(): void {}
	public function reload(): void {}
}

final class ConversationPresetRepositoryDouble implements IAgentComponentPresetRepository {
	public function __construct(private array $presets) {}
	public function getPresets(): array { return $this->presets; }
	public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
	public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
	public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
	public function removePreset(string $id): void { unset($this->presets[$id]); }
}

final class ConversationResourceFactoryDouble implements IAgentResourceFactory {
	public function __construct(private readonly SessionMemoryAgentResource $memory) {}
	public function createResource(string $type): ?IAgentResource {
		return $type === SessionMemoryAgentResource::getName() ? $this->memory : null;
	}
}
