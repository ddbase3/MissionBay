<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use PHPUnit\Framework\TestCase;
use MissionBay\Agent\AgentMemoryFactory;
use MissionBay\Api\IAgentMemory;
use Base3\Api\IClassMap;

final class AgentMemoryFactoryTest extends TestCase {

	public function testCreateMemoryReturnsInstanceForValidType(): void {
		$memory = new class implements IAgentMemory {
			public static function getName(): string { return 'stubmemory'; }
			public function loadNodeHistory(string $nodeId): array { return []; }
			public function appendNodeHistory(string $nodeId, array $message): void {}
			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
			public function resetNodeHistory(string $nodeId): void {}
			public function getPriority(): int { return 0; }
		};

		$classmap = $this->makeClassMapReturning($memory);

		$f = new AgentMemoryFactory($classmap);
		$out = $f->createMemory('nomemory');

		$this->assertSame($memory, $out);
		$this->assertInstanceOf(IAgentMemory::class, $out);
	}

	public function testCreateMemoryThrowsIfClassmapReturnsNull(): void {
		$classmap = $this->makeClassMapReturning(null);

		$f = new AgentMemoryFactory($classmap);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Memory type 'x' could not be instantiated or is invalid");

		$f->createMemory('x');
	}

	public function testCreateMemoryThrowsIfClassmapReturnsWrongType(): void {
		$classmap = $this->makeClassMapReturning(new \stdClass());

		$f = new AgentMemoryFactory($classmap);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Memory type 'y' could not be instantiated or is invalid");

		$f->createMemory('y');
	}

	private function makeClassMapReturning(mixed $instance): IClassMap {
		return new class($instance) implements IClassMap {

			private mixed $instance;

			public function __construct(mixed $instance) {
				$this->instance = $instance;
			}

			public function instantiate(string $class) {
				return null;
			}

			public function &getInstances(array $criteria = []) {
				$out = [];

				$iface = (string)($criteria['interface'] ?? '');
				$name = (string)($criteria['name'] ?? '');

				// AgentMemoryFactory ruft: getInstanceByInterfaceName(IAgentMemory::class, $type)
				// In deinem Projekt scheint das über getInstances(criteria) zu laufen.
				// Wir emulieren das minimal: wenn interface passt, liefern wir die eine Instanz.
				if ($iface === IAgentMemory::class && $name !== '') {
					if ($this->instance !== null) {
						$out[] = $this->instance;
					}
				}

				return $out;
			}

			public function getPlugins() {
				return [];
			}

			// Convenience: falls deine echte ClassMap das als Methode hat und der Factory es nutzt.
			public function getInstanceByInterfaceName(string $interface, string $name) {
				return $this->instance;
			}
		};
	}
}
