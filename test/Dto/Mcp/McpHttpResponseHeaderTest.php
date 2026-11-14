<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\Test\Dto\Mcp;

use Base3\MissionBay\Dto\Mcp\McpHttpResponse;
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
