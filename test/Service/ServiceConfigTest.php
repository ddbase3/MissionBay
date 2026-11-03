<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use MissionBay\Service\ServiceConfig;
use PHPUnit\Framework\TestCase;

final class ServiceConfigTest extends TestCase {

	public function testSettingsNormalizeIdentifiersAndDefaultName(): void {
		$config = ServiceConfig::fromSettings('Fallback', [
			'id' => ' MY MODEL! ', 'name' => ' ', 'serviceType' => ' LLM ',
			'connection' => ' PROVIDER-1 ', 'driver' => ' OpenAI-Chat ',
			'model' => '  model-v1 ', 'enabled' => 'off'
		]);
		$this->assertSame('mymodel', $config->getId());
		$this->assertSame('mymodel', $config->getName());
		$this->assertSame('llm', $config->getServiceType());
		$this->assertSame('provider-1', $config->getConnectionId());
		$this->assertSame('openai-chat', $config->getDriver());
		$this->assertSame('model-v1', $config->getModel());
		$this->assertFalse($config->isEnabled());
		$this->assertSame($config->toSettings(), $config->toDisplayArray());
	}

	public function testConnectionOwnedSecretsCannotBeStoredInServiceOptions(): void {
		$config = ServiceConfig::fromSettings('s1', [
			'options' => [
				'Api Key' => 'secret', 'authSecret' => 'secret', 'baseUrl' => 'https://secret.example',
				'secret_config' => ['mode' => 'fixed'], 'temperature' => 0.3,
				'maxTokens' => 2048, 'other' => null
			]
		]);
		$this->assertSame(['temperature' => 0.3, 'maxTokens' => 2048, 'other' => null], $config->getOptions());
		$this->assertTrue(ServiceConfig::isConnectionOwnedOptionKey(' Auth.Header.Name '));
		$this->assertFalse(ServiceConfig::isConnectionOwnedOptionKey('maxTokens'));
		$this->assertSame($config->getOptions(), $config->toSettings()['options']);
	}

	public function testEnabledUsesDocumentedBooleanNormalizations(): void {
		foreach (['false', 'OFF', 'no', '0', 0, false] as $value) {
			$this->assertFalse(ServiceConfig::fromSettings('x', ['enabled' => $value])->isEnabled());
		}
		foreach (['true', 'yes', 'on', '1', 1, true, null, '', 'unexpected'] as $value) {
			$this->assertTrue(ServiceConfig::fromSettings('x', ['enabled' => $value])->isEnabled());
		}
	}

	public function testNonArrayOptionsAndMissingFieldsUseDefaults(): void {
		$config = ServiceConfig::fromSettings('Hello_', ['options' => 'invalid']);
		$this->assertSame('hello_', $config->getId());
		$this->assertSame([], $config->getOptions());
		$this->assertSame('', $config->getConnectionId());
		$this->assertSame('', $config->getModel());
		$this->assertTrue($config->isEnabled());
	}
}
