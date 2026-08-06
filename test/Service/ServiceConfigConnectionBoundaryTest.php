<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use MissionBay\Service\ServiceConfig;
use PHPUnit\Framework\TestCase;

final class ServiceConfigConnectionBoundaryTest extends TestCase {

	public function testConnectionOwnedOptionsAreRemovedFromServiceConfiguration(): void {
		$config = new ServiceConfig(
			'image-service',
			'Image Service',
			'image',
			'mistral-api',
			'mistral-image',
			'mistral-small-latest',
			true,
			[
				'toolChoice' => 'required',
				'endpoint' => 'https://duplicate.example.test',
				'api_key' => 'duplicate-secret',
				'authType' => 'bearer'
			]
		);

		$this->assertSame([
			'toolChoice' => 'required'
		], $config->getOptions());
	}

	public function testConnectionOwnedOptionNamesAreRecognizedAcrossNamingStyles(): void {
		foreach([
			'endpoint',
			'baseUrl',
			'base_url',
			'apiKey',
			'api_key',
			'apikey',
			'authType',
			'auth_type',
			'authHeaderName',
			'auth_header_name',
			'authSecret',
			'auth_secret',
			'secretMode',
			'secret_value'
		] as $key) {
			$this->assertTrue(ServiceConfig::isConnectionOwnedOptionKey($key), $key);
		}
	}
}
