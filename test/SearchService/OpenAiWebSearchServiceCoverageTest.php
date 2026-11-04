<?php declare(strict_types=1);

namespace MissionBay\Test\SearchService;

use AssistantFoundation\Api\IAiProvider;
use Base3\Api\IClassMap;
use Base3\Event\Api\IEventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\SearchService\OpenAiWebSearchService;
use MissionBay\Transport\OpenAiTransport;
use PHPUnit\Framework\TestCase;

final class OpenAiWebSearchServiceCoverageTest extends TestCase {

	private function service(?IAiProvider $provider = null): OpenAiWebSearchService {
		$service = new OpenAiWebSearchService(
			$this->createStub(IClassMap::class),
			new AiProviderRequestEventDispatcher($this->createStub(IEventManager::class))
		);
		if ($provider !== null) {
			(new \ReflectionProperty($service, 'provider'))->setValue($service, $provider);
		}
		return $service;
	}

	private function payload(string $query, array $options): array {
		$service = $this->service();
		return (new \ReflectionMethod($service, 'buildPayload'))->invoke($service, $query, $options);
	}

	public function testAdapterUsesOpenAiTransport(): void {
		$service = $this->service();
		$this->assertSame('openaiwebsearchservice', $service::getName());
		$this->assertSame(OpenAiTransport::getName(), (new \ReflectionMethod($service, 'getProviderName'))->invoke($service));
	}

	public function testDefaultModelAndEndpointAreDefined(): void {
		$service = $this->service();
		$this->assertSame('gpt-5.5', (new \ReflectionMethod($service, 'getDefaultModel'))->invoke($service));
		$this->assertSame('https://api.openai.com', (new \ReflectionMethod($service, 'getDefaultEndpoint'))->invoke($service));
	}

	public function testWhitespaceSearchQueryIsRejectedWithoutNetwork(): void {
		$this->expectException(\RuntimeException::class);
		$this->service()->search('    ');
	}

	public function testDefaultSearchPayloadUsesTheWebSearchTool(): void {
		$this->assertSame([
			'model' => 'gpt-5.5',
			'tools' => [['type' => 'web_search']],
			'input' => 'example query'
		], $this->payload('example query', []));
	}

	public function testExplicitModelOverridesDefault(): void {
		$this->assertSame('custom-model', $this->payload('question', ['model' => 'custom-model'])['model']);
	}

	public function testWebAccessFlagCanBeExplicitlyDisabled(): void {
		$this->assertFalse($this->payload('question', ['external_web_access' => false])['tools'][0]['external_web_access']);
	}

	public function testAllowedAndBlockedDomainsArePassedToWebSearchTool(): void {
		$payload = $this->payload('question', ['allowed_domains' => 'example.org, docs.example.org', 'blocked_domains' => ['tracker.example.net']]);
		$this->assertSame(['example.org', 'docs.example.org'], $payload['tools'][0]['filters']['allowed_domains']);
		$this->assertSame(['tracker.example.net'], $payload['tools'][0]['filters']['blocked_domains']);
	}

	public function testToolChoiceAndSearchContextArePassedThrough(): void {
		$payload = $this->payload('question', ['tool_choice' => 'auto', 'search_context_size' => 'high', 'return_token_budget' => '8000']);
		$this->assertSame('auto', $payload['tool_choice']);
		$this->assertSame('high', $payload['tools'][0]['search_context_size']);
		$this->assertSame('8000', $payload['tools'][0]['return_token_budget']);
	}

	public function testInvalidSourceEntriesCannotBecomeResults(): void {
		$service = $this->service();
		$normalized = (new \ReflectionMethod($service, 'normalizeResponse'))->invoke($service, 'question', [
			'output_text' => 'Answer',
			'sources' => [null, 'wrong', ['url' => 42], ['url' => 'https://example.org', 'title' => 'Example']]
		]);
		$this->assertSame('Answer', $normalized['answer']);
		$this->assertCount(1, $normalized['results']);
		$this->assertSame('https://example.org', $normalized['results'][0]['url']);
	}

	public function testMissingSourceFieldsAreNormalizedToEmptyStrings(): void {
		$service = $this->service();
		$normalized = (new \ReflectionMethod($service, 'normalizeResponse'))->invoke($service, 'question', [
			'output_text' => 'Answer', 'sources' => [['url' => 'https://example.org', 'title' => null, 'snippet' => 3, 'source' => false]]
		]);
		$this->assertSame(['title' => '', 'url' => 'https://example.org', 'snippet' => '', 'source' => ''], $normalized['results'][0]);
	}

	public function testSearchResultDelegatesToProviderWithoutChangingQuery(): void {
		$provider = $this->createStub(IAiProvider::class);
		$provider->method('request')->willReturnCallback(function (string $path, array $payload, array $options): array {
			$this->assertSame('/v1/responses', $path);
			$this->assertSame('clean question', $payload['input']);
			$this->assertSame('gpt-5.5', $payload['model']);
			return ['output_text' => 'A grounded answer', 'sources' => [['url' => 'https://example.org']]];
		});
		$service = $this->service($provider);
		$search = $service->search('  clean question  ');
		$this->assertSame('clean question', $search['query']);
		$this->assertSame('A grounded answer', $search['answer']);
		$this->assertSame('https://example.org', $search['results'][0]['url']);
	}
}
