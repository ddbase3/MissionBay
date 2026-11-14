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

namespace Base3\MissionBay\Test\Mcp;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentResourceProvider;
use Base3\MissionBay\Mcp\McpResourceCatalog;
use PHPUnit\Framework\TestCase;

final class McpResourceCatalogTest extends TestCase {

	public function testConcreteAndTemplateResourcesAreDistinctAndDeduplicated(): void {
		$definitions = [
			['uri' => ' missionbay://status ', 'size' => '42', 'description' => ' Current status ', 'annotations' => ['audience' => ['assistant']]],
			['uri' => 'missionbay://status', 'title' => 'duplicate'],
			['uriTemplate' => 'missionbay://users/{id}', 'name' => 'User', 'mimeType' => 'text/plain'],
			['uriTemplate' => 'missionbay://users/{id}'],
			['title' => 'no locator'],
			false
		];
		$catalog = $this->catalog([$this->provider($definitions)]);
		$resources = $catalog->listResources()['resources'];
		$this->assertCount(1, $resources);
		$this->assertSame('missionbay://status', $resources[0]['uri']);
		$this->assertSame('missionbay-status', $resources[0]['name']);
		$this->assertSame('application/json', $resources[0]['mimeType']);
		$this->assertSame(42, $resources[0]['size']);
		$this->assertSame('Current status', $resources[0]['description']);
		$templates = $catalog->listResourceTemplates()['resourceTemplates'];
		$this->assertCount(1, $templates);
		$this->assertSame('missionbay://users/{id}', $templates[0]['uriTemplate']);
		$this->assertSame('User', $templates[0]['name']);
		$this->assertSame('text/plain', $templates[0]['mimeType']);
	}

	public function testReadResourceNormalizesTextBlobAndMetadata(): void {
		$catalog = $this->catalog([$this->provider([], [
			'contents' => [
				['text' => 'Hello', 'mimeType' => ' text/plain ', '_meta' => ['source' => 'one']],
				['uri' => ' missionbay://other ', 'blob' => 'YQ=='],
				['uri' => ' ', 'text' => 'fallback'],
				['uri' => 'missionbay://skip', 'unrecognized' => 'value']
			], '_meta' => ['request' => 1]
		])]);
		$result = $catalog->readResource('  missionbay://item  ');
		$this->assertCount(3, $result['contents']);
		$this->assertSame('missionbay://item', $result['contents'][0]['uri']);
		$this->assertSame('Hello', $result['contents'][0]['text']);
		$this->assertSame('text/plain', $result['contents'][0]['mimeType']);
		$this->assertSame('missionbay://other', $result['contents'][1]['uri']);
		$this->assertSame('YQ==', $result['contents'][1]['blob']);
		$this->assertSame('missionbay://item', $result['contents'][2]['uri']);
		$this->assertSame(['request' => 1], $result['_meta']);
	}

	public function testResourcesAndTemplatesUseIndependentPagination(): void {
		$definitions = [];
		for ($index = 0; $index < 51; $index++) {
			$definitions[] = ['uri' => 'missionbay://' . $index, 'uriTemplate' => 'missionbay://item/{' . $index . '}'];
		}
		$catalog = $this->catalog([$this->provider($definitions)]);
		$this->assertSame('50', $catalog->listResources()['nextCursor']);
		$this->assertSame('50', $catalog->listResourceTemplates()['nextCursor']);
		$this->assertSame('missionbay://50', $catalog->listResources('50')['resources'][0]['uri']);
		$this->assertSame('missionbay://item/{50}', $catalog->listResourceTemplates('50')['resourceTemplates'][0]['uriTemplate']);
	}

	public function testInvalidCursorAndMissingUriAreRejected(): void {
		$catalog = $this->catalog([]);
		foreach (['x', '-1', '3.5'] as $cursor) {
			foreach (['listResources', 'listResourceTemplates'] as $method) {
				try {
					$catalog->$method($cursor);
					$this->fail('Invalid cursor accepted.');
				}
				catch (\InvalidArgumentException $exception) {
					$this->assertStringContainsString('cursor', $exception->getMessage());
				}
			}
		}
		try {
			$catalog->readResource('  ');
			$this->fail('Missing URI accepted.');
		}
		catch (\InvalidArgumentException $exception) {
			$this->assertStringContainsString('uri', $exception->getMessage());
		}
	}

	public function testUnknownResourceAndEmptyProviderContentsAreRejected(): void {
		foreach ([$this->catalog([]), $this->catalog([$this->provider([], ['contents' => [['mimeType' => 'text/plain']]])])] as $catalog) {
			try {
				$catalog->readResource('missionbay://unknown');
				$this->fail('Resource read without valid contents must fail.');
			}
			catch (\InvalidArgumentException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
	}

	private function provider(array $definitions, ?array $result = null): IAgentResourceProvider {
		$provider = $this->createStub(IAgentResourceProvider::class);
		$provider->method('getResourceDefinitions')->willReturn($definitions);
		$provider->method('readResource')->willReturn($result);
		return $provider;
	}

	private function catalog(array $providers): McpResourceCatalog {
		return new McpResourceCatalog($providers, $this->createStub(IAgentContext::class), $this->createStub(ILogger::class));
	}
}
