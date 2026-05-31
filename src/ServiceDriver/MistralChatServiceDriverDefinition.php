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

final class MistralChatServiceDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'mistralchatservicedriverdefinition';
	}

	public function getDriver(): string {
		return 'mistral-chat';
	}

	public function getServiceType(): string {
		return 'llm';
	}

	public function getLabel(): string {
		return 'Mistral Chat';
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
					'default' => 'mistral-medium-2508',
					'required' => true
				],
				'temperature' => [
					'type' => 'number',
					'label' => 'Temperature',
					'default' => 0.3
				],
				'maxTokens' => [
					'type' => 'integer',
					'label' => 'Max tokens',
					'default' => 4000
				],
				'topP' => [
					'type' => 'number',
					'label' => 'Top P',
					'default' => 1
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'llm',
			'driver' => 'mistral-chat',
			'model' => 'mistral-medium-2508',
			'enabled' => true,
			'options' => [
				'temperature' => 0.3,
				'maxTokens' => 4000,
				'topP' => 1
			]
		];
	}
}
