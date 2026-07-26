<?php declare(strict_types=1);

namespace MissionBay\Test\SearchService;

use AssistantFoundation\Api\IAiProvider;
use Base3\Api\IClassMap;
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
		$service = new MistralWebSearchService(new MistralWebSearchTestClassMap($provider));
		$service->setOptions([
			'model' => 'mistral-small-2603',
			'endpoint' => 'https://api.mistral.ai',
			'apikey' => 'test-key'
		]);

		return $service;
	}
}

final class MistralWebSearchTestProvider implements IAiProvider {

	public string $path = '';

	/** @var array<string,mixed> */
	public array $payload = [];

	/** @var array<string,mixed> */
	private array $options = [];

	/**
	 * @param array<string,mixed> $response
	 */
	public function __construct(
		private readonly array $response
	) {}

	public static function getName(): string {
		return 'mistralwebsearchtestprovider';
	}

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function request(string $path, array $payload, array $options = []): array {
		$this->path = $path;
		$this->payload = $payload;

		return $this->response;
	}

	public function stream(string $path, array $payload, callable $onChunk, array $options = []): void {}
}

final class MistralWebSearchTestClassMap implements IClassMap {

	/** @var array<int,object> */
	private array $emptyInstances = [];

	private mixed $emptyInstance = null;

	public function __construct(
		private readonly IAiProvider $provider
	) {}

	public function instantiate(string $class) {
		return null;
	}

	public function instantiateWith(string $class, array $arguments = []) {
		return null;
	}

	public function generate($regenerate = false): void {}

	public function getApps() {
		return [];
	}

	public function &getInstances(array $criteria = []) {
		return $this->emptyInstances;
	}

	public function &getInstancesByInterface($interface) {
		return $this->emptyInstances;
	}

	public function &getInstancesByAppInterface($app, $interface, $retry = false) {
		return $this->emptyInstances;
	}

	public function &getInstanceByAppName($app, $name, $retry = false) {
		return $this->emptyInstance;
	}

	public function getClassByInterfaceName(string $interface, string $name): ?string {
		return null;
	}

	public function &getInstanceByInterfaceName($interface, $name, $retry = false) {
		if($interface === IAiProvider::class) {
			$provider = $this->provider;
			return $provider;
		}

		return $this->emptyInstance;
	}

	public function &getInstanceByAppInterfaceName($app, $interface, $name, $retry = false) {
		return $this->getInstanceByInterfaceName($interface, $name, $retry);
	}

	public function getPlugins() {
		return [];
	}
}
