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

namespace MissionBay\Resource;

use Base3\Api\IClassMap;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentInfoTopicProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Dto\AgentInfoRequest;
use MissionBay\Dto\AgentInfoResult;

/**
 * GeneralInfoAgentTool
 *
 * Central read-only information tool for agent workflows.
 *
 * The tool exposes one stable function to the LLM and delegates all
 * topic-specific logic to IAgentInfoTopicProvider implementations discovered
 * through the BASE3 class map.
 */
class GeneralInfoAgentTool extends AbstractAgentResource implements IAgentTool {

	private const TOOL_NAME = 'general_info';

	private const DEFAULT_SCOPE = 'summary';

	private const DEFAULT_LIMIT = 5;

	private const MAX_LIMIT = 25;

	private const MAX_LINKS = 25;

	private const TOPIC_DISCOVERY = 'topics';

	private const ALLOWED_SCOPES = [
		'find',
		'summary',
		'detail',
		'link'
	];

	protected IClassMap $classMap;

	/**
	 * @var IAgentInfoTopicProvider[]
	 */
	private array $providers = [];

	private bool $providersLoaded = false;

	public function __construct(IClassMap $classMap, ?string $id = null) {
		parent::__construct($id);
		$this->classMap = $classMap;
	}

	public static function getName(): string {
		return 'generalinfoagenttool';
	}

	public function getDescription(): string {
		return 'Provides a single read-only information tool with dynamic topic provider discovery.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'General Info',
			'category' => 'info',
			'tags' => ['info', 'inspection', 'readonly', 'topics', 'diagnostics'],
			'priority' => 50,
			'function' => [
				'name' => self::TOOL_NAME,
				'description' => 'Read-only information lookup. Use topic "topics" to list available information topics.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'topic' => [
							'type' => 'string',
							'description' => 'Technical topic name, for example "topics", "course", "user", "cron", "plugin".'
						],
						'query' => [
							'type' => 'string',
							'description' => 'Optional free-text query, identifier, title fragment, login, email, ref id, obj id, or provider-specific search text.'
						],
						'scope' => [
							'type' => 'string',
							'enum' => self::ALLOWED_SCOPES,
							'description' => 'Response scope. find returns candidates, summary returns an overview, detail returns focused details, link returns relevant links.'
						],
						'limit' => [
							'type' => 'integer',
							'description' => 'Maximum number of list items to return. Default: 5. Hard maximum: 25.'
						],
						'offset' => [
							'type' => 'integer',
							'description' => 'Pagination offset for list-like responses.'
						]
					],
					'required' => ['topic']
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): array {
		if ($name !== self::TOOL_NAME) {
			throw new \InvalidArgumentException("Unsupported tool: $name");
		}

		try {
			$requestOrError = $this->createRequest($arguments);

			if ($requestOrError instanceof AgentInfoResult) {
				return $requestOrError->toArray();
			}

			$request = $requestOrError;

			if ($request->isTopicDiscovery()) {
				return $this->handleTopicDiscovery($request)->toArray();
			}

			$provider = $this->resolveProvider($request->topic);
			if (!$provider) {
				return AgentInfoResult::createError(
					topic: $request->topic,
					scope: $request->scope,
					code: 'unknown_topic',
					message: 'Unsupported topic: ' . $request->topic,
					suggestions: $this->suggestTopics($request->topic)
				)->toArray();
			}

			$result = $provider->handle($request);
			$this->normalizeResult($result, $request, $provider);
			$this->enforceResultLimits($result, $request);

			return $result->toArray();
		} catch (\Throwable $e) {
			return AgentInfoResult::createError(
				topic: (string)($arguments['topic'] ?? ''),
				scope: (string)($arguments['scope'] ?? self::DEFAULT_SCOPE),
				code: 'tool_error',
				message: 'The info tool failed to process the request.'
			)->toArray();
		}
	}

	/**
	 * @param array<string, mixed> $arguments
	 * @return AgentInfoRequest|AgentInfoResult
	 */
	private function createRequest(array $arguments): AgentInfoRequest|AgentInfoResult {
		$topic = $this->normalizeToken((string)($arguments['topic'] ?? ''));
		if ($topic === '') {
			return AgentInfoResult::createError(
				topic: '',
				scope: self::DEFAULT_SCOPE,
				code: 'missing_topic',
				message: 'Missing required parameter: topic',
				suggestions: [self::TOPIC_DISCOVERY]
			);
		}

		$scope = $this->normalizeToken((string)($arguments['scope'] ?? self::DEFAULT_SCOPE));
		if ($scope === '') {
			$scope = self::DEFAULT_SCOPE;
		}

		if (!in_array($scope, self::ALLOWED_SCOPES, true)) {
			return AgentInfoResult::createError(
				topic: $topic,
				scope: $scope,
				code: 'unsupported_scope',
				message: 'Unsupported scope: ' . $scope,
				suggestions: self::ALLOWED_SCOPES
			);
		}

		$query = trim((string)($arguments['query'] ?? ''));

		$limit = (int)($arguments['limit'] ?? self::DEFAULT_LIMIT);
		$limit = max(1, min(self::MAX_LIMIT, $limit));

		$offset = (int)($arguments['offset'] ?? 0);
		$offset = max(0, $offset);

		return new AgentInfoRequest(
			topic: $topic,
			query: $query,
			scope: $scope,
			limit: $limit,
			offset: $offset
		);
	}

	private function handleTopicDiscovery(AgentInfoRequest $request): AgentInfoResult {
		$this->loadProviders();

		$items = [];
		foreach ($this->providers as $provider) {
			$aliases = $this->normalizeAliases($provider->getTopicAliases());

			$item = [
				'id' => $this->normalizeToken($provider->getTopic()),
				'title' => $provider->getTitle(),
				'subtitle' => $provider->getDescription(),
				'priority' => $provider->getPriority(),
				'provider' => $provider::getName()
			];

			if ($aliases !== []) {
				$item['aliases'] = $aliases;
			}

			$items[] = $item;
		}

		usort($items, static function(array $a, array $b): int {
			if ((int)$a['priority'] !== (int)$b['priority']) {
				return (int)$b['priority'] <=> (int)$a['priority'];
			}
			return strcmp((string)$a['id'], (string)$b['id']);
		});

		$total = count($items);
		$items = array_slice($items, $request->offset, $request->limit);

		return AgentInfoResult::createSuccess(
			topic: self::TOPIC_DISCOVERY,
			scope: $request->scope,
			message: $total > 0 ? 'Available info topics.' : 'No info topic providers available.',
			items: $items,
			paging: [
				'offset' => $request->offset,
				'limit' => $request->limit,
				'total' => $total,
				'returned' => count($items)
			]
		);
	}

	private function resolveProvider(string $topic): ?IAgentInfoTopicProvider {
		$this->loadProviders();

		$candidates = [];
		foreach ($this->providers as $provider) {
			if ($this->providerSupportsTopic($provider, $topic)) {
				$candidates[] = $provider;
			}
		}

		if ($candidates === []) {
			return null;
		}

		usort($candidates, static function(IAgentInfoTopicProvider $a, IAgentInfoTopicProvider $b): int {
			if ($a->getPriority() !== $b->getPriority()) {
				return $b->getPriority() <=> $a->getPriority();
			}
			return strcmp($a::getName(), $b::getName());
		});

		return $candidates[0];
	}

	private function loadProviders(): void {
		if ($this->providersLoaded) {
			return;
		}

		$this->providers = [];

		$instances = $this->classMap->getInstancesByInterface(IAgentInfoTopicProvider::class);
		foreach ($instances as $instance) {
			if ($instance instanceof IAgentInfoTopicProvider) {
				$this->providers[] = $instance;
			}
		}

		usort($this->providers, static function(IAgentInfoTopicProvider $a, IAgentInfoTopicProvider $b): int {
			if ($a->getPriority() !== $b->getPriority()) {
				return $b->getPriority() <=> $a->getPriority();
			}

			$topicCompare = strcmp($a->getTopic(), $b->getTopic());
			if ($topicCompare !== 0) {
				return $topicCompare;
			}

			return strcmp($a::getName(), $b::getName());
		});

		$this->providersLoaded = true;
	}

	private function providerSupportsTopic(IAgentInfoTopicProvider $provider, string $topic): bool {
		if ($provider->supports($topic)) {
			return true;
		}

		if ($this->normalizeToken($provider->getTopic()) === $topic) {
			return true;
		}

		return in_array($topic, $this->normalizeAliases($provider->getTopicAliases()), true);
	}

	private function normalizeResult(
		AgentInfoResult $result,
		AgentInfoRequest $request,
		IAgentInfoTopicProvider $provider
	): void {
		if ($result->topic === '') {
			$result->topic = $request->topic;
		}

		if ($result->scope === '') {
			$result->scope = $request->scope;
		}

		if ($result->message === '') {
			$result->message = $provider->getTitle();
		}
	}

	private function enforceResultLimits(AgentInfoResult $result, AgentInfoRequest $request): void {
		if (count($result->items) > $request->limit) {
			$total = count($result->items);
			$result->items = array_slice($result->items, 0, $request->limit);

			if ($result->paging === []) {
				$result->paging = [
					'offset' => $request->offset,
					'limit' => $request->limit,
					'total' => $total,
					'returned' => count($result->items),
					'truncated' => true
				];
			}
		}

		if (count($result->links) > self::MAX_LINKS) {
			$total = count($result->links);
			$result->links = array_slice($result->links, 0, self::MAX_LINKS);

			if ($result->paging === []) {
				$result->paging = [
					'links_total' => $total,
					'links_returned' => count($result->links),
					'links_truncated' => true
				];
			}
		}
	}

	/**
	 * @return array<int, string>
	 */
	private function suggestTopics(string $topic): array {
		$this->loadProviders();

		$suggestions = [self::TOPIC_DISCOVERY];

		foreach ($this->providers as $provider) {
			$providerTopic = $this->normalizeToken($provider->getTopic());
			if ($providerTopic !== '') {
				$suggestions[] = $providerTopic;
			}

			foreach ($this->normalizeAliases($provider->getTopicAliases()) as $alias) {
				$suggestions[] = $alias;
			}
		}

		$suggestions = array_values(array_unique($suggestions));
		sort($suggestions);

		if ($topic === '') {
			return array_slice($suggestions, 0, 10);
		}

		$ranked = [];
		foreach ($suggestions as $suggestion) {
			$ranked[] = [
				'topic' => $suggestion,
				'distance' => levenshtein($topic, $suggestion)
			];
		}

		usort($ranked, static function(array $a, array $b): int {
			if ($a['distance'] !== $b['distance']) {
				return $a['distance'] <=> $b['distance'];
			}
			return strcmp((string)$a['topic'], (string)$b['topic']);
		});

		$out = [];
		foreach (array_slice($ranked, 0, 10) as $row) {
			$out[] = $row['topic'];
		}

		return $out;
	}

	private function normalizeToken(string $value): string {
		$value = trim(mb_strtolower($value));
		return preg_replace('/\s+/u', '_', $value) ?? $value;
	}

	/**
	 * @param array<int, string> $aliases
	 * @return array<int, string>
	 */
	private function normalizeAliases(array $aliases): array {
		$out = [];

		foreach ($aliases as $alias) {
			$alias = $this->normalizeToken((string)$alias);
			if ($alias !== '') {
				$out[] = $alias;
			}
		}

		$out = array_values(array_unique($out));
		sort($out);

		return $out;
	}
}
