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

use Base3\MissionBay\SearchService\AbstractSearchService;
use Base3\AssistantFoundation\Dto\AiSearchResult;

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
