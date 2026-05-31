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

namespace MissionBay\ConnectionDriver;

use MissionBay\Api\IConnectionDriverDefinition;

final class HttpConnectionDriverDefinition implements IConnectionDriverDefinition {

	public static function getName(): string {
		return 'httpconnectiondriverdefinition';
	}

	public function getDriver(): string {
		return 'http';
	}

	public function getLabel(): string {
		return 'HTTP';
	}

	public function getConnectionType(): string {
		return 'http';
	}

	public function getConfigSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'baseUrl' => [
					'type' => 'string',
					'label' => 'Base URL',
					'required' => true
				],
				'auth.type' => [
					'type' => 'string',
					'label' => 'Auth type',
					'enum' => ['none', 'bearer', 'api-key', 'basic'],
					'default' => 'bearer'
				],
				'auth.secretMode' => [
					'type' => 'string',
					'label' => 'Secret mode',
					'enum' => ['fixed', 'env'],
					'default' => 'fixed'
				],
				'auth.secretValue' => [
					'type' => 'string',
					'label' => 'Secret value',
					'required' => false
				],
				'timeoutSeconds' => [
					'type' => 'integer',
					'label' => 'Timeout seconds',
					'default' => 60
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'type' => 'http',
			'driver' => 'http',
			'baseUrl' => '',
			'auth' => [
				'type' => 'bearer',
				'secretMode' => 'fixed',
				'secretValue' => ''
			],
			'timeoutSeconds' => 60,
			'scope' => 'global',
			'enabled' => true,
			'options' => []
		];
	}

	public function getHealthCheckSchema(): array {
		return [
			'type' => 'http',
			'method' => 'GET',
			'path' => '',
			'expectStatus' => [200]
		];
	}
}
