<?php declare(strict_types=1);

namespace MissionBay\Test\Connection;

use MissionBay\Connection\ConnectionConfig;
use PHPUnit\Framework\TestCase;

final class ConnectionConfigTest extends TestCase {

	public function testDefaultSettingsNormalizeConnectionAndDisableSecretForNone(): void {
		$config = ConnectionConfig::fromSettings('fallback', [
			'id' => ' MY Connection! ', 'name' => '', 'type' => '', 'driver' => '',
			'baseUrl' => ' https://example.test ', 'auth' => ['type' => 'invalid', 'secretValue' => 'secret'],
			'timeoutSeconds' => -5, 'scope' => '', 'enabled' => 'false', 'options' => 'bad'
		]);
		$this->assertSame('myconnection', $config->getId());
		$this->assertSame('myconnection', $config->getConnectionName());
		$this->assertSame('http', $config->getType());
		$this->assertSame('http', $config->getDriver());
		$this->assertSame('https://example.test', $config->getBaseUrl());
		$this->assertSame('none', $config->getAuthType());
		$this->assertFalse($config->hasAuthSecret());
		$this->assertSame([], $config->getAuthSecretConfig());
		$this->assertSame(60, $config->getTimeoutSeconds());
		$this->assertSame('global', $config->getScope());
		$this->assertFalse($config->isEnabled());
		$this->assertSame([], $config->getOptions());
		$this->assertSame(['type' => 'none'], $config->toSettings()['auth']);
	}

	public function testFixedSecretIsPersistedButNeverExposedInDisplayData(): void {
		$config = ConnectionConfig::fromSettings('production', [
			'name' => 'Prod', 'auth' => ['type' => 'bearer', 'secret' => ['mode' => 'fixed', 'value' => 'very-private']],
			'options' => ['proxy' => true]
		]);
		$this->assertTrue($config->hasAuthSecret());
		$this->assertSame('fixed', $config->getAuthSecretMode());
		$this->assertSame('stored value', $config->getAuthSecretSummary());
		$this->assertSame('very-private', $config->toSettings()['auth']['secret']['value']);
		$this->assertSame('', $config->toDisplayArray()['authSecretConfig']['value']);
		$this->assertTrue($config->toDisplayArray()['authSecretConfig']['configured']);
		$this->assertStringNotContainsString('very-private', json_encode($config->toDisplayArray()));
	}

	public function testEnvAndConfigurationSecretsPreserveReferenceWithoutResolving(): void {
		$env = ConnectionConfig::fromSettings('e', ['auth' => ['type' => 'api-key', 'headerName' => 'X-Key', 'secret' => ['mode' => 'env', 'value' => 'TOKEN_ENV']]]);
		$this->assertTrue($env->hasAuthSecret());
		$this->assertSame('env: TOKEN_ENV', $env->getAuthSecretSummary());
		$this->assertSame('TOKEN_ENV', $env->toDisplayArray()['secretValue']);
		$this->assertSame('X-Key', $env->getAuthHeaderName());
		$config = ConnectionConfig::createWithAuthSecretConfig('c', 'Name', 'http', 'http', '', 'bearer', ['mode' => 'config', 'section' => 'secrets', 'key' => 'token'], 1, 'tenant', true);
		$this->assertSame('configuration', $config->getAuthSecretMode());
		$this->assertTrue($config->hasAuthSecret());
		$this->assertSame('configuration: secrets/token', $config->getAuthSecretSummary());
	}

	public function testFileSecretRequiresPathAndNormalizesTrimFlag(): void {
		$missing = ConnectionConfig::createWithAuthSecretConfig('file', 'File', 'http', 'http', '', 'bearer', ['mode' => 'file', 'path' => ''], 60, 'global', true);
		$this->assertFalse($missing->hasAuthSecret());
		$file = ConnectionConfig::createWithAuthSecretConfig('file', 'File', 'http', 'http', '', 'bearer', ['mode' => 'file', 'path' => ' /var/token ', 'trim' => 'off'], 60, 'global', true);
		$this->assertTrue($file->hasAuthSecret());
		$this->assertFalse($file->getAuthSecretConfig()['trim']);
		$this->assertSame('file: /var/token', $file->getAuthSecretSummary());
	}

	public function testLegacySecretModesAndEmptyValuesRemainCompatible(): void {
		$legacy = new ConnectionConfig('old', 'Legacy', 'HTTP', 'HTTP', '', 'Bearer', 'env', 'ENV_TOKEN', 0, '', true);
		$this->assertSame(['mode' => 'env', 'name' => 'ENV_TOKEN'], $legacy->getAuthSecretConfig());
		$this->assertSame('ENV_TOKEN', $legacy->toDisplayArray()['secretValue']);
		$this->assertSame(60, $legacy->getTimeoutSeconds());
		$empty = new ConnectionConfig('empty', 'Empty', 'http', 'http', '', 'bearer', 'fixed', '', 10, 'global', true);
		$this->assertFalse($empty->hasAuthSecret());
		$this->assertSame('', $empty->getAuthSecretSummary());
	}
}
