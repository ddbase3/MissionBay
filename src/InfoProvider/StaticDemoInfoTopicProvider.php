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

namespace MissionBay\InfoProvider;

use MissionBay\Api\IAgentInfoTopicProvider;
use MissionBay\Dto\AgentInfoRequest;
use MissionBay\Dto\AgentInfoResult;

/**
 * StaticDemoInfoTopicProvider
 *
 * Simple static provider for testing the GeneralInfoAgentTool provider flow.
 *
 * This provider does not access external systems or databases. It is intended
 * as a safe integration test for topic discovery, scopes, paging, and detail
 * lookup.
 */
class StaticDemoInfoTopicProvider implements IAgentInfoTopicProvider {

	public static function getName(): string {
		return 'staticdemoinfotopicprovider';
	}

	public function getTopic(): string {
		return 'demo';
	}

	public function getTopicAliases(): array {
		return [
			'test',
			'example'
		];
	}

	public function getTitle(): string {
		return 'Demo Info';
	}

	public function getDescription(): string {
		return 'Provides static demo information for testing the agent info tool.';
	}

	public function getPriority(): int {
		return 1;
	}

	public function supports(string $topic): bool {
		$topic = $this->normalizeToken($topic);

		if ($topic === $this->getTopic()) {
			return true;
		}

		return in_array($topic, $this->getTopicAliases(), true);
	}

	public function handle(AgentInfoRequest $request): AgentInfoResult {
		return match ($request->scope) {
			'find' => $this->handleFind($request),
			'detail' => $this->handleDetail($request),
			'link' => $this->handleLink($request),
			default => $this->handleSummary($request)
		};
	}

	private function handleFind(AgentInfoRequest $request): AgentInfoResult {
		$items = $this->filterItems($request->query);
		$total = count($items);
		$items = array_slice($items, $request->offset, $request->limit);

		return AgentInfoResult::createSuccess(
			topic: $request->topic,
			scope: $request->scope,
			message: $total > 0 ? 'Demo items found.' : 'No demo items found.',
			items: $items,
			paging: [
				'offset' => $request->offset,
				'limit' => $request->limit,
				'total' => $total,
				'returned' => count($items)
			]
		);
	}

	private function handleSummary(AgentInfoRequest $request): AgentInfoResult {
		$items = $this->getItems();

		return AgentInfoResult::createSuccess(
			topic: $request->topic,
			scope: $request->scope,
			message: 'Static demo provider is available.',
			items: [
				[
					'id' => 'demo',
					'title' => 'Demo provider',
					'subtitle' => 'Use this topic to test provider discovery and all supported scopes.',
					'item_count' => count($items),
					'supported_scopes' => [
						'find',
						'summary',
						'detail',
						'link'
					]
				]
			],
			detail: [
				'topic' => $this->getTopic(),
				'aliases' => $this->getTopicAliases(),
				'provider' => self::getName()
			]
		);
	}

	private function handleDetail(AgentInfoRequest $request): AgentInfoResult {
		$query = trim($request->query);

		if ($query === '') {
			return AgentInfoResult::createError(
				topic: $request->topic,
				scope: $request->scope,
				code: 'missing_query',
				message: 'Missing query for demo detail lookup.',
				suggestions: [
					'alpha',
					'beta',
					'gamma'
				]
			);
		}

		$matches = $this->filterItems($query);

		if ($matches === []) {
			return AgentInfoResult::createError(
				topic: $request->topic,
				scope: $request->scope,
				code: 'not_found',
				message: 'No demo item found for query: ' . $query,
				suggestions: [
					'alpha',
					'beta',
					'gamma'
				]
			);
		}

		$exact = $this->findExactItem($query);
		if ($exact !== null) {
			return AgentInfoResult::createSuccess(
				topic: $request->topic,
				scope: $request->scope,
				message: 'Demo item detail.',
				detail: $exact
			);
		}

		if (count($matches) > 1) {
			return AgentInfoResult::createSuccess(
				topic: $request->topic,
				scope: $request->scope,
				message: 'Multiple demo items match the query. Use a more specific query.',
				items: array_slice($matches, 0, $request->limit),
				paging: [
					'offset' => 0,
					'limit' => $request->limit,
					'total' => count($matches),
					'returned' => min(count($matches), $request->limit)
				]
			);
		}

		return AgentInfoResult::createSuccess(
			topic: $request->topic,
			scope: $request->scope,
			message: 'Demo item detail.',
			detail: $matches[0]
		);
	}

	private function handleLink(AgentInfoRequest $request): AgentInfoResult {
		return AgentInfoResult::createSuccess(
			topic: $request->topic,
			scope: $request->scope,
			message: 'Demo provider does not expose external administration links.',
			links: [
				[
					'id' => 'demo-topic-discovery',
					'title' => 'Topic discovery',
					'description' => 'Call general_info with topic "topics" to list available info providers.',
					'tool_call' => [
						'topic' => 'topics',
						'scope' => 'find',
						'limit' => 5
					]
				]
			]
		);
	}

	private function findExactItem(string $query): ?array {
		$query = $this->normalizeToken($query);

		foreach ($this->getItems() as $item) {
			if ($this->normalizeToken((string)$item['id']) === $query) {
				return $item;
			}

			if ($this->normalizeToken((string)$item['title']) === $query) {
				return $item;
			}
		}

		return null;
	}

	private function filterItems(string $query): array {
		$query = $this->normalizeToken($query);

		if ($query === '') {
			return $this->getItems();
		}

		$out = [];
		foreach ($this->getItems() as $item) {
			$haystack = $this->normalizeToken(implode(' ', [
				(string)$item['id'],
				(string)$item['title'],
				(string)$item['subtitle'],
				implode(' ', $item['tags'] ?? [])
			]));

			if (str_contains($haystack, $query)) {
				$out[] = $item;
			}
		}

		return $out;
	}

	private function getItems(): array {
		return [
			[
				'id' => 'alpha',
				'title' => 'Alpha Demo Item',
				'subtitle' => 'Simple candidate used for find and detail tests.',
				'tags' => [
					'demo',
					'find',
					'detail'
				],
				'status' => 'ok'
			],
			[
				'id' => 'beta',
				'title' => 'Beta Demo Item',
				'subtitle' => 'Second candidate used for paging and ambiguity tests.',
				'tags' => [
					'demo',
					'paging',
					'candidate'
				],
				'status' => 'ok'
			],
			[
				'id' => 'gamma',
				'title' => 'Gamma Demo Item',
				'subtitle' => 'Third candidate used for compact result testing.',
				'tags' => [
					'demo',
					'summary',
					'compact'
				],
				'status' => 'ok'
			]
		];
	}

	private function normalizeToken(string $value): string {
		$value = trim(mb_strtolower($value));
		return preg_replace('/\s+/u', '_', $value) ?? $value;
	}
}
