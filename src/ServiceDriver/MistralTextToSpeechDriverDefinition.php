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

final class MistralTextToSpeechDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'mistraltexttospeechdriverdefinition';
	}

	public function getDriver(): string {
		return 'mistral-tts';
	}

	public function getServiceType(): string {
		return 'tts';
	}

	public function getLabel(): string {
		return 'Mistral Text-to-Speech';
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
					'default' => 'voxtral-mini-tts-2603',
					'required' => true
				],
				'voice' => [
					'type' => 'string',
					'label' => 'Voice ID',
					'default' => ''
				],
				'responseFormat' => [
					'type' => 'string',
					'label' => 'Audio format',
					'enum' => ['mp3', 'opus', 'flac', 'wav', 'pcm'],
					'default' => 'mp3'
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'tts',
			'driver' => 'mistral-tts',
			'model' => 'voxtral-mini-tts-2603',
			'enabled' => true,
			'options' => [
				'voice' => '',
				'responseFormat' => 'mp3'
			]
		];
	}
}
