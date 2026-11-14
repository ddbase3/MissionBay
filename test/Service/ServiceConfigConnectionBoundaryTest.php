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
