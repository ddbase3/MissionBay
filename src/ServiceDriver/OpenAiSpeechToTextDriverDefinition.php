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
use MissionBay\Api\ISpeechToTextDriver;
use MissionBay\Speech\OpenAiSpeechToTextDriver;

final class OpenAiSpeechToTextDriverDefinition implements IServiceDriverDefinition {

	private const DEFAULT_PROMPT = 'Deutschsprachige Chatnachricht, frei diktiert in natürlicher Alltagssprache. Erwartet werden vollständige Sätze mit deutscher Groß- und Kleinschreibung sowie passender Zeichensetzung. Namen, Zahlen, Datumsangaben, E-Mail-Adressen, URLs, Produktnamen und technische Begriffe können vorkommen.';

	public static function getName(): string {
		return 'openaispeechtotextdriverdefinition';
	}

	public function getDriver(): string {
		return 'openai-stt';
	}

	public function getServiceType(): string {
		return 'stt';
	}

	public function getLabel(): string {
		return 'OpenAI Realtime Speech-to-Text';
	}

	public function requiresConnection(): bool {
		return true;
	}

	public function getSupportedConnectionTypes(): array {
		return ['http'];
	}

	public function getImplementationInterface(): string {
		return ISpeechToTextDriver::class;
	}

	public function getImplementationName(): string {
		return OpenAiSpeechToTextDriver::getName();
	}

	public function getConfigSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'model' => [
					'type' => 'string',
					'label' => 'Realtime transcription model',
					'default' => 'gpt-live-transcribe',
					'required' => true
				],
				'languages' => [
					'type' => 'array',
					'label' => 'Languages',
					'default' => ['de']
				],
				'keywords' => [
					'type' => 'array',
					'label' => 'Required words',
					'default' => ['ILIAS']
				],
				'delay' => [
					'type' => 'string',
					'label' => 'Transcription delay',
					'enum' => ['low', 'medium', 'high'],
					'default' => 'low'
				],
				'noiseReduction' => [
					'type' => 'string',
					'label' => 'Noise reduction',
					'enum' => ['near_field', 'far_field'],
					'default' => 'far_field'
				],
				'clientSecretTtlSeconds' => [
					'type' => 'integer',
					'label' => 'Client secret TTL (seconds)',
					'default' => 120
				],
				'prompt' => [
					'type' => 'string',
					'label' => 'Transcription prompt',
					'default' => self::DEFAULT_PROMPT
				],
				'finalizationTimeoutMs' => [
					'type' => 'integer',
					'label' => 'Finalization timeout (ms)',
					'default' => 10000
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'stt',
			'driver' => 'openai-stt',
			'model' => 'gpt-live-transcribe',
			'enabled' => true,
			'options' => [
				'languages' => ['de'],
				'keywords' => ['ILIAS'],
				'delay' => 'low',
				'noiseReduction' => 'far_field',
				'clientSecretTtlSeconds' => 120,
				'prompt' => self::DEFAULT_PROMPT,
				'finalizationTimeoutMs' => 10000
			]
		];
	}
}
