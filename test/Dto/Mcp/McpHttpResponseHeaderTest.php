<?php declare(strict_types=1);

namespace MissionBay\Test\Dto\Mcp;

use MissionBay\Dto\Mcp\McpHttpResponse;
use PHPUnit\Framework\TestCase;

final class McpHttpResponseHeaderTest extends TestCase {

	public function testHeaderNamesAreCanonicalizedAndBlankKeysDropped(): void {
		$response = new McpHttpResponse(202, [' Content-Type ' => 'application/json', ' ' => 'invalid', 'X-Request-Id' => 'abc'], 'ok');
		$this->assertSame(202, $response->getStatusCode());
		$this->assertSame('ok', $response->getBody());
		$this->assertSame(['content-type' => 'application/json', 'x-request-id' => 'abc'], $response->getHeaders());
	}

	public function testHeaderLookupIgnoresCaseAndWhitespace(): void {
		$response = new McpHttpResponse(200, ['X-Trace' => 'one'], '');
		$this->assertSame('one', $response->getHeader(' x-TRACE '));
		$this->assertSame('fallback', $response->getHeader('Absent', 'fallback'));
		$this->assertSame('', $response->getHeader('Absent'));
	}

	public function testLastHeaderWinsForEquivalentNames(): void {
		$response = new McpHttpResponse(200, ['X-Trace' => 'first', ' x-trace ' => 'last'], '');
		$this->assertSame('last', $response->getHeader('X-TRACE'));
		$this->assertCount(1, $response->getHeaders());
	}

	public function testContentTypeDropsParametersAndNormalizesCase(): void {
		$response = new McpHttpResponse(200, ['Content-Type' => ' Application/JSON ; charset=utf-8'], '');
		$this->assertSame('application/json', $response->getContentType());
	}

	public function testContentTypeWithoutParametersIsNormalized(): void {
		$response = new McpHttpResponse(200, ['CONTENT-TYPE' => ' Text/Event-Stream '], '');
		$this->assertSame('text/event-stream', $response->getContentType());
	}

	public function testMissingContentTypeIsEmpty(): void {
		$response = new McpHttpResponse(204, [], '');
		$this->assertSame('', $response->getContentType());
	}
}
