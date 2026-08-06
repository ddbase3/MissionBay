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

use AssistantFoundation\Api\IServiceDriverDefinition;
use MissionBay\Api\ITextToSpeechDriver;
use MissionBay\Speech\OpenAiTextToSpeechDriver;

final class OpenAiTextToSpeechDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'openaitexttospeechdriverdefinition';
	}

	public function getDriver(): string {
		return 'openai-tts';
	}

	public function getServiceType(): string {
		return 'tts';
	}

	public function getLabel(): string {
		return 'OpenAI Text-to-Speech';
	}

	public function requiresConnection(): bool {
		return true;
	}

	public function getSupportedConnectionTypes(): array {
		return ['http'];
	}

	public function getImplementationInterface(): string {
		return ITextToSpeechDriver::class;
	}

	public function getImplementationName(): string {
		return OpenAiTextToSpeechDriver::getName();
	}

	public function getConfigSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'model' => [
					'type' => 'string',
					'label' => 'Model',
					'default' => 'gpt-4o-mini-tts',
					'required' => true
				],
				'voice' => [
					'type' => 'string',
					'label' => 'Voice',
					'default' => 'alloy',
					'required' => true
				],
				'responseFormat' => [
					'type' => 'string',
					'label' => 'Audio format',
					'enum' => ['mp3', 'opus', 'aac', 'flac', 'wav'],
					'default' => 'mp3'
				],
				'speed' => [
					'type' => 'number',
					'label' => 'Speed',
					'minimum' => 0.25,
					'maximum' => 4.0,
					'default' => 1.0
				],
				'instructions' => [
					'type' => 'string',
					'label' => 'Instructions',
					'default' => ''
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'tts',
			'driver' => 'openai-tts',
			'model' => 'gpt-4o-mini-tts',
			'enabled' => true,
			'options' => [
				'voice' => 'alloy',
				'responseFormat' => 'mp3',
				'speed' => 1.0,
				'instructions' => ''
			]
		];
	}
}
