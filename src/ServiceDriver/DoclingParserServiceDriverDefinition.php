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

namespace MissionBay\ServiceDriver;

use MissionBay\Api\IServiceDriverDefinition;

final class DoclingParserServiceDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'doclingparserservicedriverdefinition';
	}

	public function getDriver(): string {
		return 'docling-parser';
	}

	public function getServiceType(): string {
		return 'parser';
	}

	public function getLabel(): string {
		return 'Docling Parser';
	}

	public function requiresConnection(): bool {
		return true;
	}

	public function getSupportedConnectionTypes(): array {
		return ['http'];
	}

	public function getConfigSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'model' => [
					'type' => 'string',
					'label' => 'Engine',
					'default' => 'default',
					'required' => true
				],
				'contentType' => [
					'type' => 'string',
					'label' => 'Content type',
					'default' => 'application/x-agent-content-json'
				],
				'supportedTypes' => [
					'type' => 'array',
					'label' => 'Supported types',
					'default' => ['file']
				],
				'priority' => [
					'type' => 'integer',
					'label' => 'Priority',
					'default' => 45
				],
				'fileField' => [
					'type' => 'string',
					'label' => 'Multipart file field',
					'default' => 'file'
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'parser',
			'driver' => 'docling-parser',
			'model' => 'default',
			'enabled' => true,
			'options' => [
				'contentType' => 'application/x-agent-content-json',
				'supportedTypes' => ['file'],
				'priority' => 45,
				'fileField' => 'file',
				'timeoutSeconds' => 90,
				'connectTimeoutSeconds' => 20,
				'maxBytes' => 0
			]
		];
	}
}
