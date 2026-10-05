<?php declare(strict_types=1);

namespace MissionBay\Test\ServiceDriver;

use MissionBay\ServiceDriver\MistralChatServiceDriverDefinition;
use PHPUnit\Framework\TestCase;

final class MistralChatServiceDriverDefinitionTest extends TestCase {

	public function testDefaultModelIsMistralMedium35(): void {
		$definition = new MistralChatServiceDriverDefinition();
		$schema = $definition->getConfigSchema();
		$defaults = $definition->getDefaultConfig();

		$this->assertSame('mistral-medium-3-5', $schema['properties']['model']['default'] ?? null);
		$this->assertSame('mistral-medium-3-5', $defaults['model'] ?? null);
	}
}
