<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use PHPUnit\Framework\TestCase;
use MissionBay\Agent\AgentMemoryFactory;
use MissionBay\Api\IAgentMemory;
use Base3\Api\IClassMap;
use Base3\Test\Core\ClassMapStub;

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
		$cm = new ClassMapStub();

		if (is_object($instance)) {
			$cm->registerInstance(
				$instance,
				'nomemory',
				[\MissionBay\Api\IAgentMemory::class]
			);
		}

		return $cm;
	}
}
