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

		return $this->normalizeResponse($query, $result);
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
		$parts = $this->collectStringsRecursively($response);

		usort($parts, static function(string $a, string $b): int {
			return mb_strlen($b) <=> mb_strlen($a);
		});

		foreach($parts as $part) {
			$part = trim($part);

			if($part === '') {
				continue;
			}

			if($this->looksLikeAssistantOutput($part)) {
				return $part;
			}
		}

		return '';
	}

	private function looksLikeAssistantOutput(string $text): bool {
		if(str_starts_with($text, '{') || str_starts_with($text, '[')) {
			return true;
		}

		return mb_strlen($text) >= 120;
	}
}
