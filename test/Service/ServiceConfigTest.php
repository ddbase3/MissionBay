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

use Base3\MissionBay\Service\ServiceConfig;
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
