<?php declare(strict_types=1);

namespace MissionBay\Test\SearchService;

use MissionBay\SearchService\AbstractSearchService;
use AssistantFoundation\Dto\AiSearchResult;

final class AbstractSearchOptionsProbe extends AbstractSearchService {

	public function __construct() {}

	public static function getName(): string { return 'abstractsearchoptionsprobe'; }
	public function searchResult(string $query, array $options = []): AiSearchResult { throw new \BadMethodCallException('Not needed in an options-only probe.'); }
	public function search(string $query, array $options = []): array { throw new \BadMethodCallException('Not needed in an options-only probe.'); }
	protected function getProviderName(): string { return 'test-search-provider'; }
	protected function getDefaultEndpoint(): string { return 'https://example.test/search'; }
	protected function getDefaultModel(): string { return 'search-default'; }

	public function model(array $options): string { return $this->getModel($options); }
	public function endpoint(array $options): string { return $this->getEndpoint($options); }
	public function apiKey(array $options): string { return $this->getApiKey($options); }
	public function integer(array $options, string $key, int $default): int { return $this->getIntOption($options, $key, $default); }
	public function boolean(array $options, string $key, bool $default): bool { return $this->getBoolOption($options, $key, $default); }
	public function requestOptions(array $options): array { return $this->buildRequestOptions($options); }
	public function stringList(array $options, string $key): array { return $this->getStringListOption($options, $key); }
	public function recursiveStrings(mixed $value): array { return $this->collectStringsRecursively($value); }
	public function recursiveUrls(mixed $value): array { return $this->collectUrlItemsRecursively($value); }
}
