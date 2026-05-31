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

final class OpenAiCompatibleEmbeddingServiceDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'openaicompatibleembeddingservicedriverdefinition';
	}

	public function getDriver(): string {
		return 'openai-compatible-embedding';
	}

	public function getServiceType(): string {
		return 'embedding';
	}

	public function getLabel(): string {
		return 'OpenAI-Compatible Embedding';
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
				'dimensions' => [
					'type' => 'integer',
					'label' => 'Dimensions',
					'default' => 1536
				],
				'batchSize' => [
					'type' => 'integer',
					'label' => 'Batch size',
					'default' => 100
				],
				'normalizeVectors' => [
					'type' => 'boolean',
					'label' => 'Normalize vectors',
					'default' => false
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'embedding',
			'driver' => 'openai-compatible-embedding',
			'model' => '',
			'enabled' => true,
			'options' => [
				'dimensions' => 1536,
				'batchSize' => 100,
				'normalizeVectors' => false
			]
		];
	}
}
