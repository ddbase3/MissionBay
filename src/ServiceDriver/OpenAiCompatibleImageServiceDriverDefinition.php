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

final class OpenAiCompatibleImageServiceDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'openaicompatibleimageservicedriverdefinition';
	}

	public function getDriver(): string {
		return 'openai-compatible-image';
	}

	public function getServiceType(): string {
		return 'image';
	}

	public function getLabel(): string {
		return 'OpenAI-Compatible Image';
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
				'size' => [
					'type' => 'string',
					'label' => 'Size',
					'default' => '1024x1024'
				],
				'quality' => [
					'type' => 'string',
					'label' => 'Quality',
					'default' => 'auto'
				],
				'outputFormat' => [
					'type' => 'string',
					'label' => 'Output format',
					'default' => 'png'
				],
				'background' => [
					'type' => 'string',
					'label' => 'Background',
					'default' => 'auto'
				],
				'numberOfImages' => [
					'type' => 'integer',
					'label' => 'Number of images',
					'default' => 1
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'image',
			'driver' => 'openai-compatible-image',
			'model' => '',
			'enabled' => true,
			'options' => [
				'size' => '1024x1024',
				'quality' => 'auto',
				'outputFormat' => 'png',
				'background' => 'auto',
				'moderation' => 'auto',
				'numberOfImages' => 1
			]
		];
	}
}
