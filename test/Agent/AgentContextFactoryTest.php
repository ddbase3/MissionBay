<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use PHPUnit\Framework\TestCase;
use MissionBay\Agent\AgentContextFactory;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;
use Base3\Api\IClassMap;
use Base3\Test\Core\ClassMapStub;

final class AgentContextFactoryTest extends TestCase {

	public function testCreateContextReturnsContextAndAppliesMemoryAndVars(): void {
		$memory = new class implements IAgentMemory {
			public static function getName(): string { return 'stubmemory'; }
			public function loadNodeHistory(string $nodeId): array { return []; }
			public function appendNodeHistory(string $nodeId, array $message): void {}
			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
			public function resetNodeHistory(string $nodeId): void {}
			public function getPriority(): int { return 0; }
		};

		$context = new class implements IAgentContext {
			private IAgentMemory $memory;
			private array $vars = [];

			public static function getName(): string { return 'stubcontext'; }

			public function getMemory(): IAgentMemory {
				return $this->memory;
			}

			public function setMemory(IAgentMemory $memory): void {
				$this->memory = $memory;
			}

			public function setVar(string $key, mixed $value): void {
				$this->vars[$key] = $value;
			}

			public function getVar(string $key): mixed {
				return $this->vars[$key] ?? null;
			}

			public function forgetVar(string $key): void {
				unset($this->vars[$key]);
			}

			public function listVars(): array {
				return array_keys($this->vars);
			}
		};

		$classmap = $this->makeClassMapReturning($context);

		$f = new AgentContextFactory($classmap);

		$out = $f->createContext('agentcontext', $memory, [
			'a' => 1,
			'b' => 'x',
			'c' => ['y'],
		]);

		$this->assertSame($context, $out);
		$this->assertSame($memory, $out->getMemory());
		$this->assertSame(1, $out->getVar('a'));
		$this->assertSame('x', $out->getVar('b'));
		$this->assertSame(['y'], $out->getVar('c'));
	}

	public function testCreateContextDoesNotCallSetMemoryWhenMemoryNull(): void {
		$context = new class implements IAgentContext {
			public bool $setMemoryCalled = false;
			private array $vars = [];
			private ?IAgentMemory $memory = null;

			public static function getName(): string { return 'stubcontext'; }

			public function getMemory(): IAgentMemory {
				if ($this->memory === null) {
					// Für den Test reicht ein Dummy, falls jemand es doch abfragt.
					$this->memory = new class implements IAgentMemory {
						public static function getName(): string { return 'dummy'; }
						public function loadNodeHistory(string $nodeId): array { return []; }
						public function appendNodeHistory(string $nodeId, array $message): void {}
						public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
						public function resetNodeHistory(string $nodeId): void {}
						public function getPriority(): int { return 0; }
					};
				}
				return $this->memory;
			}

			public function setMemory(IAgentMemory $memory): void {
				$this->setMemoryCalled = true;
				$this->memory = $memory;
			}

			public function setVar(string $key, mixed $value): void {
				$this->vars[$key] = $value;
			}

			public function getVar(string $key): mixed {
				return $this->vars[$key] ?? null;
			}

			public function forgetVar(string $key): void {
				unset($this->vars[$key]);
			}

			public function listVars(): array {
				return array_keys($this->vars);
			}
		};

		$classmap = $this->makeClassMapReturning($context);

		$f = new AgentContextFactory($classmap);
		$out = $f->createContext('agentcontext', null, ['x' => 123]);

		$this->assertSame($context, $out);
		$this->assertFalse($context->setMemoryCalled);
		$this->assertSame(123, $out->getVar('x'));
	}

	public function testCreateContextThrowsIfClassmapReturnsNull(): void {
		$classmap = $this->makeClassMapReturning(null);

		$f = new AgentContextFactory($classmap);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Context type 'x' could not be instantiated or is invalid");

		$f->createContext('x');
	}

	public function testCreateContextThrowsIfClassmapReturnsWrongType(): void {
		$classmap = $this->makeClassMapReturning(new \stdClass());

		$f = new AgentContextFactory($classmap);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Context type 'y' could not be instantiated or is invalid");

		$f->createContext('y');
	}

	private function makeClassMapReturning(mixed $instance): IClassMap {
		$cm = new ClassMapStub();

		if (is_object($instance)) {
			$cm->registerInstance(
				$instance,
				'agentcontext',
				[\MissionBay\Api\IAgentContext::class]
			);
		}

		return $cm;
	}
}
