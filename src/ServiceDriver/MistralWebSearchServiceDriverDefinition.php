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

final class MistralWebSearchServiceDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'mistralwebsearchservicedriverdefinition';
	}

	public function getDriver(): string {
		return 'mistral-websearch';
	}

	public function getServiceType(): string {
		return 'search';
	}

	public function getLabel(): string {
		return 'Mistral Web Search';
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
					'label' => 'Model',
					'default' => '',
					'required' => true
				],
				'maxResults' => [
					'type' => 'integer',
					'label' => 'Max results',
					'default' => 10
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'search',
			'driver' => 'mistral-websearch',
			'model' => '',
			'enabled' => true,
			'options' => [
				'maxResults' => 10
			]
		];
	}
}
