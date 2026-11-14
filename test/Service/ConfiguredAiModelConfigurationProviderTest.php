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

use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Api\IServiceDriverDefinition;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Service\ConfiguredAiModelConfigurationProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfiguredAiModelConfigurationProviderTest extends TestCase {

	public function testOptionsContainOnlyLlmsSortedByLabel(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturn([
			'b' => ['serviceType' => 'llm', 'name' => 'Zulu', 'driver' => 'openai', 'model' => 'test', 'enabled' => true],
			'a' => ['serviceType' => 'llm', 'name' => 'Alpha', 'driver' => 'openai', 'model' => 'test', 'enabled' => false],
			'c' => ['serviceType' => 'search', 'name' => 'Search']
		]);
		$provider = new ConfiguredAiModelConfigurationProvider($settings, $this->createStub(IAgentConfigValueResolver::class), $this->createStub(IClassMap::class));
		$this->assertSame(['a', 'b'], array_column($provider->getOptions(), 'id'));
		$this->assertFalse($provider->getOptions()[0]['enabled']);
		$this->assertFalse($provider->has('a'));
	}

	public function testConfiguredModelResolvesConnectionSecretAndNormalizesOptions(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturnCallback(static function(string $group, string $id, array $default = []): array {
			return match($group) {
				'service-llm' => ['id' => 'test', 'name' => 'Test', 'serviceType' => 'llm', 'connection' => 'connection', 'driver' => 'openai', 'model' => 'model', 'options' => ['maxTokens' => 200, 'topP' => 0.8, 'chatCompletionPath' => '/v1/chat/completions']],
				'connection' => ['id' => 'connection', 'baseUrl' => 'https://example.test/v1', 'auth' => ['type' => 'bearer', 'secret' => ['mode' => 'fixed', 'value' => 'not-logged']]],
				default => $default
			};
		});
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturn('resolved-secret');
		$driver = $this->createStub(IServiceDriverDefinition::class);
		$driver->method('getServiceType')->willReturn('llm');
		$driver->method('getImplementationInterface')->willReturn(IAiChatModel::class);
		$driver->method('getDriver')->willReturn('openai');
		$classes = $this->createStub(IClassMap::class);
		$classes->method('getInstancesByInterface')->willReturn([$driver]);
		$provider = new ConfiguredAiModelConfigurationProvider($settings, $resolver, $classes);
		$model = $provider->get('test');
		$this->assertSame('model', $model->getModel());
		$this->assertSame('https://example.test/v1/chat/completions', $model->getEndpoint());
		$this->assertSame('resolved-secret', $model->getApiKey());
		$this->assertSame(200, $model->getOptions()['max_tokens']);
		$this->assertSame(0.8, $model->getOptions()['top_p']);
		$this->assertArrayNotHasKey('chatCompletionPath', $model->getOptions());
	}

	public function testMissingConfiguredModelIsRejected(): void {
		$provider = new ConfiguredAiModelConfigurationProvider($this->createStub(ISettingsStore::class), $this->createStub(IAgentConfigValueResolver::class), $this->createStub(IClassMap::class));
		$this->expectException(RuntimeException::class);
		$provider->get('missing');
	}
}
