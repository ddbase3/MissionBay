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

namespace MissionBay\SearchService;

use AssistantFoundation\Dto\AiSearchResult;
use MissionBay\Ai\AiResultNormalizer;
use MissionBay\Transport\OpenAiTransport;
use RuntimeException;

class OpenAiWebSearchService extends AbstractSearchService {

	public static function getName(): string {
		return 'openaiwebsearchservice';
	}

	protected function getProviderName(): string {
		return OpenAiTransport::getName();
	}

	protected function getDefaultEndpoint(): string {
		return 'https://api.openai.com';
	}

	protected function getDefaultModel(): string {
		return 'gpt-5.5';
	}

	public function search(string $query, array $options = []): array {
		$result = $this->searchResult($query, $options);

		return [
			'query' => $result->getQuery(),
			'answer' => $result->getAnswer(),
			'results' => $result->getResults(),
			'citations' => $result->getCitations(),
			'raw' => $result->getRaw()
		];
	}

	public function searchResult(string $query, array $options = []): AiSearchResult {
		$startedAt = microtime(true);
		$query = trim($query);

		if($query === '') {
			throw new RuntimeException('Missing search query.');
		}

		$runtimeOptions = array_merge($this->options, $options);
		$result = $this->getProvider()->request(
			'/v1/responses',
			$this->buildPayload($query, $runtimeOptions),
			$this->buildRequestOptions($runtimeOptions)
		);

		$normalized = $this->normalizeResponse($query, $result);

		return new AiSearchResult(
			$query,
			(string)($normalized['answer'] ?? ''),
			is_array($normalized['results'] ?? null) ? $normalized['results'] : [],
			is_array($normalized['citations'] ?? null) ? $normalized['citations'] : [],
			AiResultNormalizer::metadata('search', $result, [
				'provider' => $this->getProviderName(),
				'model' => $this->getModel($runtimeOptions),
				'adapter' => static::getName(),
				'started_at' => $startedAt,
				'usage_metrics' => [
					'search_queries' => 1,
					'search_results' => count(is_array($normalized['results'] ?? null) ? $normalized['results'] : [])
				]
			], $startedAt),
			$result
		);
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @return array<string,mixed>
	 */
	private function buildPayload(string $query, array $runtimeOptions): array {
		$model = $this->getModel($runtimeOptions);

		if($model === '') {
			throw new RuntimeException('Missing model name for OpenAI web search service.');
		}

		$tool = [
			'type' => 'web_search'
		];

		$searchContextSize = trim((string)($runtimeOptions['search_context_size'] ?? ''));
		if($searchContextSize !== '') {
			$tool['search_context_size'] = $searchContextSize;
		}

		if(array_key_exists('external_web_access', $runtimeOptions)) {
			$tool['external_web_access'] = $this->getBoolOption($runtimeOptions, 'external_web_access', true);
		}

		$returnTokenBudget = trim((string)($runtimeOptions['return_token_budget'] ?? ''));
		if($returnTokenBudget !== '') {
			$tool['return_token_budget'] = $returnTokenBudget;
		}

		$filters = [];
		$allowedDomains = $this->getStringListOption($runtimeOptions, 'allowed_domains');
		$blockedDomains = $this->getStringListOption($runtimeOptions, 'blocked_domains');

		if($allowedDomains !== []) {
			$filters['allowed_domains'] = $allowedDomains;
		}

		if($blockedDomains !== []) {
			$filters['blocked_domains'] = $blockedDomains;
		}

		if($filters !== []) {
			$tool['filters'] = $filters;
		}

		$payload = [
			'model' => $model,
			'tools' => [
				$tool
			],
			'input' => $query
		];

		$toolChoice = trim((string)($runtimeOptions['tool_choice'] ?? ''));
		if($toolChoice !== '') {
			$payload['tool_choice'] = $toolChoice;
		}

		return $payload;
	}

	/**
	 * @param array<string,mixed> $result
	 * @return array<string,mixed>
	 */
	private function normalizeResponse(string $query, array $result): array {
		$answer = '';

		if(is_string($result['output_text'] ?? null)) {
			$answer = $result['output_text'];
		}

		if($answer === '') {
			$strings = $this->collectStringsRecursively($result);

			usort($strings, static function(string $a, string $b): int {
				return mb_strlen($b) <=> mb_strlen($a);
			});

			$answer = trim((string)($strings[0] ?? ''));
		}

		$sources = is_array($result['sources'] ?? null)
			? $this->normalizeUrlItems($result['sources'])
			: [];

		$citations = $this->collectUrlItemsRecursively($result);

		return [
			'query' => $query,
			'answer' => $answer,
			'results' => $sources !== [] ? $sources : $citations,
			'citations' => $citations,
			'raw' => $result
		];
	}

	/**
	 * @param array<int,mixed> $items
	 * @return array<int,array<string,string>>
	 */
	private function normalizeUrlItems(array $items): array {
		$out = [];

		foreach($items as $item) {
			if(!is_array($item) || !is_string($item['url'] ?? null)) {
				continue;
			}

			$out[] = [
				'title' => is_string($item['title'] ?? null) ? $item['title'] : '',
				'url' => $item['url'],
				'snippet' => is_string($item['snippet'] ?? null) ? $item['snippet'] : '',
				'source' => is_string($item['source'] ?? null) ? $item['source'] : ''
			];
		}

		return $out;
	}
}
