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

final class OpenAiWebSearchServiceDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'openaiwebsearchservicedriverdefinition';
	}

	public function getDriver(): string {
		return 'openai-websearch';
	}

	public function getServiceType(): string {
		return 'search';
	}

	public function getLabel(): string {
		return 'OpenAI Web Search';
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
					'default' => 'gpt-5.5',
					'required' => true
				],
				'searchContextSize' => [
					'type' => 'string',
					'label' => 'Search context size',
					'default' => 'medium'
				],
				'externalWebAccess' => [
					'type' => 'boolean',
					'label' => 'External web access',
					'default' => true
				],
				'returnTokenBudget' => [
					'type' => 'string',
					'label' => 'Return token budget',
					'default' => ''
				],
				'toolChoice' => [
					'type' => 'string',
					'label' => 'Tool choice',
					'default' => 'auto'
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'search',
			'driver' => 'openai-websearch',
			'model' => 'gpt-5.5',
			'enabled' => true,
			'options' => [
				'searchContextSize' => 'medium',
				'externalWebAccess' => true,
				'returnTokenBudget' => '',
				'toolChoice' => 'auto',
				'allowedDomains' => [],
				'blockedDomains' => []
			]
		];
	}
}
