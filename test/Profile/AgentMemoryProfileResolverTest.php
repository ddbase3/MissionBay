<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationScope;
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

	public function testMemoryProfileDoesNotReadLegacyEntries(): void {
		$store = $this->settingsStore([
			AgentMemoryProfileResolver::SETTINGS_GROUP => [
				'legacy-combined' => [
					'label' => 'Legacy combined',
					'enabled' => true,
					'entries' => [
						['preset' => 'session-main', 'role' => 'conversation-memory']
					]
				]
			]
		]);
		$resolver = new AgentMemoryProfileResolver($store, $this->presetRepository(), $this->resourceFactory());

		$this->assertSame([], $resolver->getProfile('legacy-combined')[AgentMemoryProfileResolver::PRESET_FIELD]);
		$this->expectException(\RuntimeException::class);
		$resolver->resolveComponents('legacy-combined');
	}

	public function testMemoryProfileRequiresExactlyOneConversationMemory(): void {
		$store = $this->settingsStore([
			AgentMemoryProfileResolver::SETTINGS_GROUP => [
				'invalid' => [
					'enabled' => true,
					'memories' => ['session-main', 'session-copy']
				]
			]
		]);
		$repository = $this->presetRepository();
		$repository->savePreset('session-copy', [
			'id' => 'session-copy',
			'type' => 'testconversationmemory',
			'enabled' => true
		]);
		$resolver = new AgentMemoryProfileResolver($store, $repository, $this->resourceFactory());

		$this->expectException(\RuntimeException::class);
		$resolver->resolveComponents('invalid');
	}

	/** @param array<string,array<string,array<string,mixed>>> $groups */
	private function settingsStore(array $groups): ISettingsStore {
		return new AgentMemoryProfileResolverTestDouble1($groups);
	}

	private function presetRepository(): IAgentComponentPresetRepository {
		return new AgentMemoryProfileResolverTestDouble2();
	}

	private function resourceFactory(): IAgentResourceFactory {
		return new AgentMemoryProfileResolverTestDouble3();
	}
}

require_once __DIR__ . '/AgentMemoryProfileResolverTestDouble1.php';

require_once __DIR__ . '/AgentMemoryProfileResolverTestDouble2.php';

require_once __DIR__ . '/AgentMemoryProfileResolverTestDouble3.php';
