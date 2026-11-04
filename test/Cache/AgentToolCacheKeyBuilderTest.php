<?php declare(strict_types=1);

namespace MissionBay\Test\Cache;

use AssistantFoundation\Dto\AgentAction;
use MissionBay\Cache\AgentToolCacheKeyBuilder;
use PHPUnit\Framework\TestCase;

final class AgentToolCacheKeyBuilderTest extends TestCase {

	public function testAssociativeArgumentOrderIsCanonicalAcrossNesting(): void {
		$builder = new AgentToolCacheKeyBuilder();
		$a = $builder->build('mission', 'source', 'lookup', ['b' => 2, 'nested' => ['z' => 1, 'a' => 4], 'a' => 1], 'private');
		$b = $builder->build('mission', 'source', 'lookup', ['a' => 1, 'nested' => ['a' => 4, 'z' => 1], 'b' => 2], 'private');
		$this->assertSame($a, $b);
		$this->assertSame(['a' => 1, 'b' => 2, 'nested' => ['a' => 4, 'z' => 1]], $a['normalized_arguments']);
		$this->assertStringContainsString('agent-tool-result:', $a['key']);
		$this->assertSame(64, strlen($a['arguments_hash']));
	}

	public function testListOrderAndCacheScopeAffectKeyIndependently(): void {
		$builder = new AgentToolCacheKeyBuilder();
		$first = $builder->build('n', 'source', 'tool', ['ids' => [1, 2]], 'user', 'v1');
		$reordered = $builder->build('n', 'source', 'tool', ['ids' => [2, 1]], 'user', 'v1');
		$anotherScope = $builder->build('n', 'source', 'tool', ['ids' => [1, 2]], 'global', 'v1');
		$anotherVariant = $builder->build('n', 'source', 'tool', ['ids' => [1, 2]], 'user', 'v2');
		$this->assertSame([1, 2], $first['normalized_arguments']['ids']);
		$this->assertNotSame($first['key'], $reordered['key']);
		$this->assertNotSame($first['arguments_hash'], $reordered['arguments_hash']);
		$this->assertNotSame($first['key'], $anotherScope['key']);
		$this->assertSame($first['arguments_hash'], $anotherScope['arguments_hash']);
		$this->assertNotSame($first['key'], $anotherVariant['key']);
	}

	public function testArrayConvertibleObjectsAreNormalizedWithoutMutatingArguments(): void {
		$action = new AgentAction('c1', 'tool_call', 'read', ['x' => 1]);
		$input = ['action' => $action];
		$result = (new AgentToolCacheKeyBuilder())->build('n', 'x', 'read', $input, 'scope');
		$expected = $action->toArray();
		ksort($expected);
		$this->assertSame($expected, $result['normalized_arguments']['action']);
		$this->assertSame($action, $input['action']);
	}

	public function testJsonSerializableObjectsAreNormalized(): void {
		$serializable = $this->createStub(\JsonSerializable::class);
		$serializable->method('jsonSerialize')->willReturn(['z' => 3, 'a' => 1]);
		$result = (new AgentToolCacheKeyBuilder())->build('n', 'x', 'tool', ['data' => $serializable], 'scope');
		$this->assertSame(['a' => 1, 'z' => 3], $result['normalized_arguments']['data']);
	}

	public function testNonSerializableObjectsAreRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new AgentToolCacheKeyBuilder())->build('n', 'x', 'tool', ['object' => new \stdClass()], 'scope');
	}

	public function testResourceArgumentsAreRejected(): void {
		$handle = fopen('php://memory', 'r+');
		try {
			$this->expectException(\InvalidArgumentException::class);
			(new AgentToolCacheKeyBuilder())->build('n', 'x', 'tool', ['handle' => $handle], 'scope');
		} finally {
			fclose($handle);
		}
	}

	public function testInvalidUtf8IsRejectedDuringEncoding(): void {
		$this->expectException(\RuntimeException::class);
		(new AgentToolCacheKeyBuilder())->build('n', 'x', 'tool', ['bad' => "\xB1"], 'scope');
	}
}
