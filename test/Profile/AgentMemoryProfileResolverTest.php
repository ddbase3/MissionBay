<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentMemoryProfileResolver;
use MissionBay\Resource\AbstractAgentResource;
use MissionBay\Resource\AgentContext\Text\StaticTextContextAgentResource;
use PHPUnit\Framework\TestCase;

final class AgentMemoryProfileResolverTest extends TestCase {

	public function testMemoryProfileResolvesOnlyConfiguredConversationMemoryPresets(): void {
		$store = $this->settingsStore([
			AgentMemoryProfileResolver::SETTINGS_GROUP => [
				'chat-history' => [
					'label' => 'Chat history',
					'enabled' => true,
					'memories' => ['session-main']
				]
			]
		]);
		$resolver = new AgentMemoryProfileResolver($store, $this->presetRepository(), $this->resourceFactory());

		$this->assertSame(['session-main'], array_column($resolver->getPresetOptions(), 'id'));
		$this->assertSame([[
			'preset' => 'session-main',
			'attach_as' => ['memory'],
			'enabled' => true,
			'order' => 10,
			'memory_profile' => 'chat-history',
			'memory_config' => [
				'enabled' => true,
				'read_enabled' => true,
				'write_enabled' => true
			]
		]], $resolver->resolveComponents('chat-history'));
	}

	public function testContextProfileResolvesOnlyConfiguredContextContributorPresets(): void {
		$store = $this->settingsStore([
			AgentContextProfileResolver::SETTINGS_GROUP => [
				'page-context' => [
					'label' => 'Page context',
					'enabled' => true,
					'contexts' => ['current-time', 'static-text', 'user-prefs']
				]
			]
		]);
		$resolver = new AgentContextProfileResolver($store, $this->presetRepository(), $this->resourceFactory());

		$this->assertSame(['current-time', 'static-text', 'user-prefs'], array_column($resolver->getPresetOptions(), 'id'));
		$this->assertSame(['current-time', 'static-text', 'user-prefs'], array_column($resolver->resolveComponents('page-context'), 'preset'));
		$this->assertSame([['context'], ['context'], ['context']], array_column($resolver->resolveComponents('page-context'), 'attach_as'));
	}

	public function testLegacyCombinedProfileIsSplitByActualRuntimeContract(): void {
		$store = $this->settingsStore([
			AgentMemoryProfileResolver::SETTINGS_GROUP => [
				'legacy-combined' => [
					'label' => 'Legacy combined',
					'enabled' => true,
					'entries' => [
						['preset' => 'session-main', 'role' => 'conversation-memory'],
						['preset' => 'current-time', 'role' => 'auto'],
						['preset' => 'user-prefs', 'role' => 'auto']
					]
				]
			]
		]);
		$memory = new AgentMemoryProfileResolver($store, $this->presetRepository(), $this->resourceFactory());
		$context = new AgentContextProfileResolver($store, $this->presetRepository(), $this->resourceFactory());

		$this->assertSame(['session-main'], $memory->getProfile('legacy-combined')['presets']);
		$this->assertSame(['current-time', 'user-prefs'], $context->getProfile('legacy-combined')['presets']);
		$this->assertTrue($context->getProfile('legacy-combined')['legacy_derived']);
	}

	/** @param array<string,array<string,array<string,mixed>>> $groups */
	private function settingsStore(array $groups): ISettingsStore {
		return new class($groups) implements ISettingsStore {
			public function __construct(private array $groups) {}
			public function get(string $group, string $name, array $default = []): array { return $this->groups[$group][$name] ?? $default; }
			public function set(string $group, string $name, array $settings): void { $this->groups[$group][$name] = $settings; }
			public function has(string $group, string $name): bool { return isset($this->groups[$group][$name]); }
			public function remove(string $group, string $name): void { unset($this->groups[$group][$name]); }
			public function getGroup(string $group): array { return $this->groups[$group] ?? []; }
			public function save(): void {}
			public function reload(): void {}
		};
	}

	private function presetRepository(): IAgentComponentPresetRepository {
		return new class implements IAgentComponentPresetRepository {
			private array $presets = [
				'session-main' => ['id' => 'session-main', 'label' => 'Main session memory', 'type' => 'testconversationmemory', 'enabled' => true, 'config' => ['namespace' => 'main', 'max' => 20]],
				'current-time' => ['id' => 'current-time', 'label' => 'Current time', 'type' => 'testcontextcontributor', 'enabled' => true, 'config' => []],
				'static-text' => ['id' => 'static-text', 'label' => 'Static text', 'type' => 'statictextcontextagentresource', 'enabled' => true, 'config' => ['text' => 'Reusable system context.']],
				'user-prefs' => ['id' => 'user-prefs', 'label' => 'User preferences', 'type' => 'testdualtoolcontext', 'enabled' => true, 'config' => ['priority' => 20]],
				'tool-only' => ['id' => 'tool-only', 'label' => 'Tool only', 'type' => 'testtoolonly', 'enabled' => true, 'config' => []]
			];
			public function getPresets(): array { return $this->presets; }
			public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
			public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
			public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
			public function removePreset(string $id): void { unset($this->presets[$id]); }
		};
	}

	private function resourceFactory(): IAgentResourceFactory {
		return new class implements IAgentResourceFactory {
			public function createResource(string $type): ?IAgentResource {
				return match ($type) {
					'testconversationmemory' => new class('conversation') extends AbstractAgentResource implements IAgentConversationMemory {
						public static function getName(): string { return 'testconversationmemory'; }
						public function getDescription(): string { return 'Test conversation memory.'; }
						public function loadNodeHistory(string $nodeId): array { return []; }
						public function appendNodeHistory(string $nodeId, array $message): void {}
						public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
						public function resetNodeHistory(string $nodeId): void {}
						public function getPriority(): int { return 80; }
					},
					'testcontextcontributor', 'testdualtoolcontext' => new class('context') extends AbstractAgentResource implements IAgentContextContributor {
						public static function getName(): string { return 'testcontextcontributor'; }
						public function getDescription(): string { return 'Test context contributor.'; }
						public function contribute(IAgentContext $context): iterable { return [new AgentInstructionBlock('test', 'context')]; }
						public function getPriority(): int { return 20; }
					},
					'statictextcontextagentresource' => new StaticTextContextAgentResource(
						new class implements IAgentConfigValueResolver {
							public function resolveValue(array|string|int|float|bool|null $config): mixed { return $config; }
						},
						'static-text'
					),
					default => null
				};
			}
		};
	}
}
