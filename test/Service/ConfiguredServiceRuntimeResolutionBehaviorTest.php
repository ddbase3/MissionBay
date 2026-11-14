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
use Base3\MissionBay\Connection\ConnectionConfig;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use Base3\MissionBay\ServiceDriver\OpenAiChatServiceDriverDefinition;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfiguredServiceRuntimeResolutionBehaviorTest extends TestCase {

	public function testServiceListingIsSortedAndFiltersDisabledAndUnrelatedRecords(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturn([
			'z-service' => $this->serviceSettings('llm'),
			'a-service' => $this->serviceSettings('llm'),
			'disabled' => array_merge($this->serviceSettings('llm'), ['enabled' => false]),
			'search' => $this->serviceSettings('search'),
			'broken' => 'not an array',
			0 => $this->serviceSettings('llm')
		]);
		$this->assertSame(['a-service', 'z-service'], $this->resolver($settings)->listServiceIds('llm', 'llm'));
	}

	public function testServiceListingUsesConfiguredNormalizedId(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturn([
			'legacy-id' => array_merge($this->serviceSettings('llm'), ['id' => ' Better ID ! '])
		]);
		$this->assertSame(['betterid'], $this->resolver($settings)->listServiceIds('llm', 'llm'));
	}

	public function testCreatesEnabledServiceWithNormalizedIdentifiers(): void {
		$config = $this->resolver()->createServiceConfig(' My Service ! ', $this->serviceSettings('llm'), 'llm', true);
		$this->assertSame('myservice', $config->getId());
		$this->assertSame('provider', $config->getConnectionId());
		$this->assertSame('openai-chat', $config->getDriver());
		$this->assertSame('model-1', $config->getModel());
	}

	public function testEmptyServiceIdIsRejected(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Configured service id is empty.');
		$this->resolver()->createServiceConfig('   ', $this->serviceSettings('llm'), 'llm');
	}

	public function testWrongServiceTypeIsRejected(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Service config has wrong service type:');
		$this->resolver()->createServiceConfig('search-1', $this->serviceSettings('search'), 'llm');
	}

	public function testDisabledServiceIsRejectedWhenEnabledServiceIsRequired(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Service config is disabled:');
		$this->resolver()->createServiceConfig('disabled', array_merge($this->serviceSettings('llm'), ['enabled' => false]), 'llm', true);
	}

	public function testMissingConnectionIsRejected(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Service config has no connection:');
		$this->resolver()->createServiceConfig('service', array_merge($this->serviceSettings('llm'), ['connection' => '']), 'llm');
	}

	public function testMissingDriverIsRejected(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Service config has no driver:');
		$this->resolver()->createServiceConfig('service', array_merge($this->serviceSettings('llm'), ['driver' => '']), 'llm');
	}

	public function testMissingPersistedServiceIsRejected(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn([]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Service config not found: llm/missing');
		$this->resolver($settings)->loadServiceConfig('llm', 'missing', 'llm');
	}

	public function testPersistedServiceMustBeEnabled(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn(array_merge($this->serviceSettings('llm'), ['enabled' => false]));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Service config is disabled:');
		$this->resolver($settings)->loadServiceConfig('llm', 'service', 'llm');
	}

	public function testEmptyConnectionIdIsRejected(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Connection id is empty.');
		$this->resolver()->loadConnectionConfig(' ! ');
	}

	public function testMissingConnectionIsRejectedBeforeDriverResolution(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn([]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Connection config not found: connection/provider');
		$this->resolver($settings)->loadConnectionConfig('provider');
	}

	public function testDisabledConnectionIsRejected(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn(['baseUrl' => 'https://example.org', 'enabled' => false]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Connection config is disabled:');
		$this->resolver($settings)->loadConnectionConfig('provider');
	}

	public function testMissingConnectionBaseUrlIsRejected(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn(['baseUrl' => '  ', 'enabled' => true]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Connection config has no base URL:');
		$this->resolver($settings)->loadConnectionConfig('provider');
	}

	public function testEnabledConnectionWithBaseUrlCanBeLoaded(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn(['baseUrl' => ' https://example.org/v1 ', 'enabled' => true]);
		$config = $this->resolver($settings)->loadConnectionConfig(' PROVIDER ');
		$this->assertSame('provider', $config->getId());
		$this->assertSame('https://example.org/v1', $config->getBaseUrl());
	}

	public function testDriverDefinitionRequiresMatchingTypeDriverAndInterface(): void {
		$definition = new OpenAiChatServiceDriverDefinition();
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn([$definition]);
		$this->assertSame($definition, $this->resolver(null, $classMap)->resolveDriverDefinition(' OPENAI-CHAT ', 'LLM', IAiChatModel::class));
	}

	public function testDriverDefinitionRejectsWrongInterface(): void {
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn([new OpenAiChatServiceDriverDefinition()]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('No service driver definition for type');
		$this->resolver(null, $classMap)->resolveDriverDefinition('openai-chat', 'llm', 'Invalid\\Interface');
	}

	public function testUnauthenticatedConnectionNeedsNoSecret(): void {
		$config = ConnectionConfig::fromSettings('provider', ['auth' => ['type' => 'none']]);
		$this->assertNull($this->resolver()->resolveConnectionSecret($config));
	}

	public function testAuthenticatedConnectionWithoutSecretIsRejected(): void {
		$config = ConnectionConfig::fromSettings('provider', ['auth' => ['type' => 'bearer']]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Connection config has no secret config:');
		$this->resolver()->resolveConnectionSecret($config);
	}

	public function testResolvedSecretIsTrimmed(): void {
		$config = ConnectionConfig::fromSettings('provider', ['auth' => [
			'type' => 'bearer', 'secret' => ['mode' => 'env', 'name' => 'MISSIONBAY_TEST_TOKEN']
		]]);
		$secretResolver = $this->createStub(IAgentConfigValueResolver::class);
		$secretResolver->method('resolveValue')->willReturn('  token-value  ');
		$this->assertSame('token-value', $this->resolver(null, null, $secretResolver)->resolveConnectionSecret($config));
	}

	public function testBlankResolvedSecretIsRejected(): void {
		$config = ConnectionConfig::fromSettings('provider', ['auth' => [
			'type' => 'bearer', 'secret' => ['mode' => 'env', 'name' => 'MISSIONBAY_TEST_TOKEN']
		]]);
		$secretResolver = $this->createStub(IAgentConfigValueResolver::class);
		$secretResolver->method('resolveValue')->willReturn('  ');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Connection secret could not be resolved:');
		$this->resolver(null, null, $secretResolver)->resolveConnectionSecret($config);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function serviceSettings(string $type): array {
		return ['serviceType' => $type, 'connection' => 'provider', 'driver' => 'openai-chat', 'model' => 'model-1', 'enabled' => true];
	}

	private function resolver(
		?ISettingsStore $settingsStore = null,
		?IClassMap $classMap = null,
		?IAgentConfigValueResolver $secretResolver = null
	): ConfiguredServiceRuntimeResolver {
		return new ConfiguredServiceRuntimeResolver(
			$settingsStore ?? $this->createStub(ISettingsStore::class),
			$classMap ?? $this->createStub(IClassMap::class),
			$secretResolver ?? $this->createStub(IAgentConfigValueResolver::class)
		);
	}
}
