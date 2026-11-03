<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Api\IServiceDriverDefinition;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Service\ConfiguredAiModelConfigurationProvider;
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
