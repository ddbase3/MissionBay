<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use MissionBay\Mcp\McpToolResultMapper;
use PHPUnit\Framework\TestCase;

final class McpToolResultMapperTest extends TestCase {

	public function testScalarResultsAreReturnedAsTextWithoutStructuredContent(): void {
		$mapper = new McpToolResultMapper();
		foreach ([
			['hello', 'hello'],
			['true', true],
			['false', false],
			['0', 0],
			['null', null]
		] as [$expected, $value]) {
			$result = $mapper->success($value);
			$this->assertSame($expected, $result['content'][0]['text']);
			$this->assertArrayNotHasKey('structuredContent', $result);
		}
	}

	public function testAssociativeResultsProvideBothTextAndStructuredContent(): void {
		$mapper = new McpToolResultMapper();
		$payload = ['status' => 'ok', 'count' => 2];
		$result = $mapper->success($payload);
		$this->assertSame($payload, $result['structuredContent']);
		$this->assertStringContainsString('"status": "ok"', $result['content'][0]['text']);
		$this->assertSame('text', $result['content'][0]['type']);
	}

	public function testListsAndEmptyArraysDoNotBecomeStructuredObjects(): void {
		$mapper = new McpToolResultMapper();
		foreach ([[], [1, 2, 3]] as $value) {
			$result = $mapper->success($value);
			$this->assertArrayNotHasKey('structuredContent', $result);
			$this->assertNotEmpty($result['content'][0]['text']);
		}
	}

	public function testStdClassIsSerializedAsStructuredContent(): void {
		$result = (new McpToolResultMapper())->success((object)['enabled' => true]);
		$this->assertSame(['enabled' => true], $result['structuredContent']);
		$this->assertStringContainsString('true', $result['content'][0]['text']);
	}

	public function testErrorResponsePreservesFailureTextAndFlag(): void {
		$this->assertSame([
			'isError' => true,
			'content' => [['type' => 'text', 'text' => 'not authorized']]
		], (new McpToolResultMapper())->error('not authorized'));
	}
}
