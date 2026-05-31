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

final class OpenAiCompatibleChatServiceDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'openaicompatiblechatservicedriverdefinition';
	}

	public function getDriver(): string {
		return 'openai-compatible-chat';
	}

	public function getServiceType(): string {
		return 'llm';
	}

	public function getLabel(): string {
		return 'OpenAI-Compatible Chat';
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
			'driver' => 'openai-compatible-chat',
			'model' => '',
			'enabled' => true,
			'options' => [
				'temperature' => 0.3,
				'maxTokens' => 4000,
				'topP' => 1
			]
		];
	}
}
