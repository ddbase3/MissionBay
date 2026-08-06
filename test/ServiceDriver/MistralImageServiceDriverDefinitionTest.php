<?php declare(strict_types=1);

namespace MissionBay\Test\ServiceDriver;

use AssistantFoundation\Api\IImageGenerationModel;
use AssistantFoundation\Api\IServiceDriverDefinition;
use MissionBay\ImageModel\MistralImageModel;
use MissionBay\ServiceDriver\MistralImageServiceDriverDefinition;
use PHPUnit\Framework\TestCase;

final class MistralImageServiceDriverDefinitionTest extends TestCase {

	public function testDescribesTheMistralImageAdapterWithoutOpenAiOnlyOptions(): void {
		$definition = new MistralImageServiceDriverDefinition();
		$schema = $definition->getConfigSchema();
		$properties = $schema['properties'];

		$this->assertInstanceOf(IServiceDriverDefinition::class, $definition);
		$this->assertSame('mistral-image', $definition->getDriver());
		$this->assertSame('image', $definition->getServiceType());
		$this->assertSame(IImageGenerationModel::class, $definition->getImplementationInterface());
		$this->assertSame(MistralImageModel::getName(), $definition->getImplementationName());
		$this->assertSame('mistral-small-latest', $properties['model']['default']);
		$this->assertSame('tool_choice', $properties['toolChoice']['runtimeKey']);
		$this->assertSame(['required', 'any', 'auto'], $properties['toolChoice']['enum']);
		$this->assertArrayNotHasKey('size', $properties);
		$this->assertArrayNotHasKey('quality', $properties);
		$this->assertArrayNotHasKey('outputFormat', $properties);
		$this->assertArrayNotHasKey('background', $properties);
	}
}
