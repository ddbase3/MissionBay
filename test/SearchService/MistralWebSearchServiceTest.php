<?php declare(strict_types=1);

namespace MissionBay\Test\SearchService;

use AssistantFoundation\Api\IAiProvider;
use Base3\Api\IClassMap;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\SearchService\MistralWebSearchService;
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
