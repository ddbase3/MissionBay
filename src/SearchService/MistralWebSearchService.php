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
use MissionBay\Transport\MistralTransport;
use RuntimeException;

class MistralWebSearchService extends AbstractSearchService {

	public static function getName(): string {
		return 'mistralwebsearchservice';
	}

	protected function getProviderName(): string {
		return MistralTransport::getName();
	}

	protected function getDefaultEndpoint(): string {
		return 'https://api.mistral.ai';
	}

	protected function getDefaultModel(): string {
		return '';
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
			'/v1/conversations',
			$this->buildPayload($query, $runtimeOptions),
			$this->buildRequestOptions($runtimeOptions)
		);

		$normalized = $this->normalizeResponse($query, $result);
		$metadata = AiResultNormalizer::metadata('search', $result, [
			'provider' => $this->getProviderName(),
			'model' => $this->getModel($runtimeOptions),
			'adapter' => static::getName(),
			'started_at' => $startedAt,
			'usage_metrics' => [
				'search_queries' => 1,
				'search_results' => count(is_array($normalized['results'] ?? null) ? $normalized['results'] : [])
			]
		], $startedAt);

		$this->dispatchProviderRequestCompleted($metadata);

		return new AiSearchResult(
			$query,
			(string)($normalized['answer'] ?? ''),
			is_array($normalized['results'] ?? null) ? $normalized['results'] : [],
			is_array($normalized['citations'] ?? null) ? $normalized['citations'] : [],
			$metadata,
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
			throw new RuntimeException('Missing model name for Mistral web search service.');
		}

		return [
			'model' => $model,
			'inputs' => [
				[
					'role' => 'user',
					'content' => $query
				]
			],
			'tools' => [
				[
					'type' => 'web_search'
				]
			]
		];
	}

	/**
	 * @param array<string,mixed> $result
	 * @return array<string,mixed>
	 */
	private function normalizeResponse(string $query, array $result): array {
		$answer = $this->extractAssistantText($result);
		$citations = $this->collectUrlItemsRecursively($result);

		return [
			'query' => $query,
			'answer' => $answer,
			'results' => $citations,
			'citations' => $citations,
			'raw' => $result
		];
	}

	/**
	 * @param array<string,mixed> $response
	 */
	private function extractAssistantText(array $response): string {
		$outputs = $response['outputs'] ?? null;

		if(!is_array($outputs)) {
			return '';
		}

		for($index = count($outputs) - 1; $index >= 0; $index--) {
			$output = $outputs[$index] ?? null;

			if(!is_array($output)) {
				continue;
			}

			if(($output['type'] ?? null) !== 'message.output') {
				continue;
			}

			if(($output['role'] ?? null) !== 'assistant') {
				continue;
			}

			$text = $this->extractMessageContent($output['content'] ?? null);

			if($text !== '') {
				return $text;
			}
		}

		return '';
	}

	private function extractMessageContent(mixed $content): string {
		if(is_string($content)) {
			return trim($content);
		}

		if(!is_array($content)) {
			return '';
		}

		$text = '';

		foreach($content as $chunk) {
			if(!is_array($chunk)) {
				continue;
			}

			if(($chunk['type'] ?? null) !== 'text') {
				continue;
			}

			if(!is_string($chunk['text'] ?? null)) {
				continue;
			}

			$text .= $chunk['text'];
		}

		return trim($text);
	}
}
