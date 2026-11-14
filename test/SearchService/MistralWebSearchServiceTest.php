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

namespace Base3\MissionBay\Test\SearchService;

use Base3\AssistantFoundation\Api\IAiProvider;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\SearchService\MistralWebSearchService;
use PHPUnit\Framework\TestCase;

final class MistralWebSearchServiceTest extends TestCase {

	public function testUsesOnlyAssistantMessageOutputAsAnswer(): void {
		$provider = new MistralWebSearchTestProvider([
			'outputs' => [
				[
					'object' => 'entry',
					'type' => 'tool.execution',
					'name' => 'web_search',
					'content' => '{"raw_search_results":{"first":{"title":"A very long raw search result that must never become the assistant answer"}}}'
				],
				[
					'object' => 'entry',
					'type' => 'message.output',
					'role' => 'assistant',
					'content' => [
						[
							'type' => 'text',
							'text' => 'Die wichtigsten Entwicklungen '
						],
						[
							'type' => 'tool_reference',
							'tool' => 'web_search',
							'title' => 'Source',
							'url' => 'https://example.test/source',
							'source' => 'example.test'
						],
						[
							'type' => 'text',
							'text' => 'sind hier zusammengefasst.'
						]
					]
				]
			]
		]);
		$service = $this->createService($provider);

		$result = $service->searchResult('Aktuelle KI-News');

		$this->assertSame('Die wichtigsten Entwicklungen sind hier zusammengefasst.', $result->getAnswer());
		$this->assertSame('https://example.test/source', $result->getCitations()[0]['url'] ?? null);
		$this->assertSame('/v1/conversations', $provider->path);
		$this->assertSame('web_search', $provider->payload['tools'][0]['type'] ?? null);
	}

	public function testDoesNotPromoteToolExecutionContentToAnswer(): void {
		$provider = new MistralWebSearchTestProvider([
			'outputs' => [
				[
					'object' => 'entry',
					'type' => 'tool.execution',
					'name' => 'web_search',
					'content' => '{"raw_search_results":{"first":{"title":"Raw result"}}}'
				]
			]
		]);
		$service = $this->createService($provider);

		$result = $service->searchResult('Aktuelle KI-News');

		$this->assertSame('', $result->getAnswer());
	}

	public function testSupportsStringContentOnAssistantMessageOutput(): void {
		$provider = new MistralWebSearchTestProvider([
			'outputs' => [
				[
					'object' => 'entry',
					'type' => 'message.output',
					'role' => 'assistant',
					'content' => 'Eine normale Antwort.'
				]
			]
		]);
		$service = $this->createService($provider);

		$result = $service->searchResult('Aktuelle KI-News');

		$this->assertSame('Eine normale Antwort.', $result->getAnswer());
	}

	private function createService(IAiProvider $provider): MistralWebSearchService {
		$service = new MistralWebSearchService(
			new MistralWebSearchTestClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$service->setOptions([
			'model' => 'mistral-small-2603',
			'endpoint' => 'https://api.mistral.ai',
			'apikey' => 'test-key'
		]);

		return $service;
	}
}

require_once __DIR__ . '/MistralWebSearchTestProvider.php';
require_once __DIR__ . '/MistralWebSearchTestClassMap.php';
