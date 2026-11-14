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

namespace Base3\MissionBay\Test\ServiceDriver;

use Base3\AssistantFoundation\Api\IImageGenerationModel;
use Base3\AssistantFoundation\Api\IServiceDriverDefinition;
use Base3\MissionBay\ImageModel\MistralImageModel;
use Base3\MissionBay\ServiceDriver\MistralImageServiceDriverDefinition;
use PHPUnit\Framework\TestCase;

final class MistralImageServiceDriverDefinitionTest extends TestCase {

	public function testDescribesTheMistralImageAdapterWithoutUnsupportedGenerationOptions(): void {
		$definition = new MistralImageServiceDriverDefinition();
		$schema = $definition->getConfigSchema();
		$properties = $schema['properties'];

		$this->assertInstanceOf(IServiceDriverDefinition::class, $definition);
		$this->assertSame('mistral-image', $definition->getDriver());
		$this->assertSame('image', $definition->getServiceType());
		$this->assertSame(IImageGenerationModel::class, $definition->getImplementationInterface());
		$this->assertSame(MistralImageModel::getName(), $definition->getImplementationName());
		$this->assertSame('mistral-small-latest', $properties['model']['default']);
		$this->assertArrayNotHasKey('toolChoice', $properties);
		$this->assertArrayNotHasKey('size', $properties);
		$this->assertArrayNotHasKey('quality', $properties);
		$this->assertArrayNotHasKey('outputFormat', $properties);
		$this->assertArrayNotHasKey('background', $properties);
		$this->assertSame([], $definition->getDefaultConfig()['options']);
	}
}
