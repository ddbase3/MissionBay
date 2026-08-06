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
use AssistantFoundation\Api\IImageGenerationModel;
use MissionBay\ImageModel\OpenAiImageModel;

final class OpenAiImageServiceDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'openaiimageservicedriverdefinition';
	}

	public function getDriver(): string {
		return 'openai-image';
	}

	public function getServiceType(): string {
		return 'image';
	}

	public function getLabel(): string {
		return 'OpenAI Image';
	}

	public function requiresConnection(): bool {
		return true;
	}

	public function getSupportedConnectionTypes(): array {
		return ['http'];
	}

	public function getImplementationInterface(): string {
		return IImageGenerationModel::class;
	}

	public function getImplementationName(): string {
		return OpenAiImageModel::getName();
	}


	public function getConfigSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'model' => [
					'type' => 'string',
					'label' => 'Model',
					'default' => 'gpt-image-2',
					'required' => true
				],
				'size' => [
					'type' => 'string',
					'label' => 'Size',
					'default' => '1024x1024',
					'runtimeKey' => 'size'
				],
				'quality' => [
					'type' => 'string',
					'label' => 'Quality',
					'enum' => ['auto', 'low', 'medium', 'high'],
					'default' => 'auto',
					'runtimeKey' => 'quality'
				],
				'outputFormat' => [
					'type' => 'string',
					'label' => 'Output format',
					'enum' => ['png', 'jpeg', 'webp'],
					'default' => 'png',
					'runtimeKey' => 'output_format'
				],
				'background' => [
					'type' => 'string',
					'label' => 'Background',
					'enum' => ['auto', 'opaque', 'transparent'],
					'default' => 'auto',
					'runtimeKey' => 'background'
				],
				'moderation' => [
					'type' => 'string',
					'label' => 'Moderation',
					'enum' => ['auto', 'low'],
					'default' => 'auto',
					'runtimeKey' => 'moderation'
				],
				'numberOfImages' => [
					'type' => 'integer',
					'label' => 'Number of images',
					'minimum' => 1,
					'default' => 1,
					'runtimeKey' => 'n'
				],
				'outputCompression' => [
					'type' => 'integer',
					'label' => 'Output compression',
					'minimum' => 0,
					'maximum' => 100,
					'runtimeKey' => 'output_compression'
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'image',
			'driver' => 'openai-image',
			'model' => 'gpt-image-2',
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
