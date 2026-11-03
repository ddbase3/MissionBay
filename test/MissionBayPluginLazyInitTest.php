<?php declare(strict_types=1);

namespace MissionBay\Test;

use Base3\Api\IContainer;
use Base3\Core\ComponentDefinition;
use MissionBay\MissionBayPlugin;
use PHPUnit\Framework\TestCase;

final class MissionBayPluginLazyInitTest extends TestCase {

	public function testInitDoesNotResolveContainerServices(): void {
		$container = new LazyInitContainer();
		$plugin = new MissionBayPlugin($container);

		$plugin->init();

		$this->assertSame(0, $container->getGetCalls());
	}

	public function testInitRegistersRuntimeServicesOnlyAsLazyFactories(): void {
		$container = new LazyInitContainer();
		$plugin = new MissionBayPlugin($container);

		$plugin->init();

		foreach ($container->getRegistrations() as $name => $registration) {
			$definition = $registration['definition'];
			$flags = $registration['flags'];

			if ($name === MissionBayPlugin::getName()) {
				$this->assertSame($plugin, $definition);
				continue;
			}

			if (($flags & IContainer::PARAMETER) === IContainer::PARAMETER) {
				if ($definition instanceof ComponentDefinition) {
					$this->assertDeclarativeValue($definition->arguments, $name . ' arguments');
					$this->assertDeclarativeValue($definition->config, $name . ' config');
					$this->assertDeclarativeValue($definition->metadata, $name . ' metadata');
				}
				else {
					$this->assertDeclarativeValue($definition, $name);
				}
				continue;
			}

			if (($flags & IContainer::ALIAS) === IContainer::ALIAS) {
				$this->assertIsString($definition, 'MissionBay aliases must reference another service lazily.');
				continue;
			}

			$this->assertInstanceOf(\Closure::class, $definition, 'MissionBay runtime service ' . $name . ' must be registered lazily.');
		}
	}

	private function assertDeclarativeValue(mixed $value, string $path): void {
		if (is_array($value)) {
			foreach ($value as $key => $item) {
				$this->assertDeclarativeValue($item, $path . '[' . (string)$key . ']');
			}
			return;
		}

		$this->assertTrue(
			$value === null || is_scalar($value),
			$path . ' must contain only scalar, null, or array values.'
		);
	}
}

require_once __DIR__ . '/LazyInitContainer.php';
