<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Base3\Api\IClassMap;
use Base3\Test\Core\ClassMapStub;
use MissionBay\Agent\AgentFlowFactory;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentResource;

#[AllowMockObjectsWithoutExpectations]
final class AgentFlowFactoryTest extends TestCase {

	public function testCreateEmptyInstantiatesFlowAndSetsContextIfProvided(): void {
		$context = $this->createMock(IAgentContext::class);

		$flow = $this->makeFlowStub();
		$classmap = $this->makeClassMapReturning($flow);

		$nodeFactory = $this->createMock(IAgentNodeFactory::class);

		$f = new AgentFlowFactory($classmap, $nodeFactory);

		$out = $f->createEmpty('someflow', $context);

		$this->assertSame($flow, $out);
		$this->assertSame($context, $flow->__ctx);
	}

	public function testCreateEmptyDoesNotSetContextWhenNull(): void {
		$flow = $this->makeFlowStub();
		$classmap = $this->makeClassMapReturning($flow);

		$nodeFactory = $this->createMock(IAgentNodeFactory::class);

		$f = new AgentFlowFactory($classmap, $nodeFactory);

		$out = $f->createEmpty('someflow', null);

		$this->assertSame($flow, $out);
		$this->assertNull($flow->__ctx);
	}

	public function testCreateFromArrayDelegatesToFlowFromArray(): void {
		$context = $this->createMock(IAgentContext::class);
		$data = ['nodes' => [['id' => 'n1']]];

		$flow = $this->makeFlowStub();
		$classmap = $this->makeClassMapReturning($flow);

		$nodeFactory = $this->createMock(IAgentNodeFactory::class);

		$f = new AgentFlowFactory($classmap, $nodeFactory);

		$out = $f->createFromArray('someflow', $data, $context);

		$this->assertSame($flow, $out);
		$this->assertSame($context, $flow->__ctx);
		$this->assertSame($data, $flow->__fromArrayArg);
		$this->assertSame(1, $flow->__fromArrayCalls);
	}

	public function testCreateEmptyThrowsIfClassmapReturnsInvalidFlow(): void {
		$classmap = $this->makeClassMapReturning(new \stdClass());

		$nodeFactory = $this->createMock(IAgentNodeFactory::class);

		$f = new AgentFlowFactory($classmap, $nodeFactory);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Flow type 'badflow' could not be instantiated or is invalid");

		$f->createEmpty('badflow', null);
	}

	private function makeFlowStub(): object {
		return new class implements IAgentFlow {

			public ?IAgentContext $__ctx = null;

			public int $__fromArrayCalls = 0;
			public ?array $__fromArrayArg = null;

			public static function getName(): string {
				return 'testflow';
			}

			public function setContext(IAgentContext $context): void {
				$this->__ctx = $context;
			}

			// This is intentionally NOT part of IAgentFlow but is used by AgentFlowFactory::createFromArray()
			public function fromArray(array $data): self {
				$this->__fromArrayCalls++;
				$this->__fromArrayArg = $data;
				return $this;
			}

			public function run(array $inputs): array {
				return [];
			}

			public function addNode(IAgentNode $node): void {
			}

			public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput): void {
			}

			public function addInitialInput(string $nodeId, string $key, mixed $value): void {
			}

			public function getInitialInputs(): array {
				return [];
			}

			public function getConnections(): array {
				return [];
			}

			public function getNextNode(string $currentNodeId, array $output): ?string {
				return null;
			}

			public function mapInputs(string $fromNodeId, string $toNodeId, array $output): array {
				return [];
			}

			public function isReady(string $nodeId, array $currentInputs): bool {
				return true;
			}

			public function addResource(IAgentResource $resource): void {
			}

			public function getResources(): array {
				return [];
			}

			public function addDockConnection(string $nodeId, string $dockName, string $resourceId): void {
			}

			public function getAllDockConnections(): array {
				return [];
			}

			public function getDockConnections(string $nodeId): array {
				return [];
			}
		};
	}

	private function makeClassMapReturning(mixed $instance): IClassMap {
		$cm = new ClassMapStub();

		if (is_object($instance)) {
			$cm->registerInstance(
				$instance,
				'someflow',
				[\MissionBay\Api\IAgentFlow::class]
			);
		}

		return $cm;
	}
}
