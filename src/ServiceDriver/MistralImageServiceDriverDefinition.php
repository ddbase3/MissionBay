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
use MissionBay\ImageModel\MistralImageModel;

final class MistralImageServiceDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'mistralimageservicedriverdefinition';
	}

	public function getDriver(): string {
		return 'mistral-image';
	}

	public function getServiceType(): string {
		return 'image';
	}

	public function getLabel(): string {
		return 'Mistral Image';
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
		return MistralImageModel::getName();
	}

	public function getConfigSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'model' => [
					'type' => 'string',
					'label' => 'Model',
					'default' => 'mistral-small-latest',
					'required' => true
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'image',
			'driver' => 'mistral-image',
			'model' => 'mistral-small-latest',
			'enabled' => true,
			'options' => []
		];
	}
}
