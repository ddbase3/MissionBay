<?php declare(strict_types=1);

namespace MissionBay\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use MissionBay\Api\IAgentInfoTopicProvider;
use MissionBay\Dto\AgentInfoRequest;
use MissionBay\Dto\AgentInfoResult;

final class AgentInfoTopicProviderTestAdminDisplay implements IDisplay {

	private const DEFAULT_SCOPE = 'summary';

	private const DEFAULT_LIMIT = 5;

	private const MAX_LIMIT = 25;

	private const ALLOWED_SCOPES = [
		'find',
		'summary',
		'detail',
		'link'
	];

	public function __construct(
		private readonly IClassMap $classmap,
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService
	) {}

	public static function getName(): string {
		return 'agentinfotopicprovidertestadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$out = strtolower((string) $out);

		if($out === 'json') {
			return $this->handleJson($final);
		}

		return $this->handleHtml();
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/AgentInfoTopicProviderTestAdminDisplay.php');

		$this->view->assign(
			'service',
			$this->linkTargetService->getLink(
				[
					'name' => self::getName(),
					'out' => 'json'
				]
			)
		);

		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string) $src));

		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final = false): string {
		$response = $this->buildJsonResponse();

		if($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string) json_encode(
			$response,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildJsonResponse(): array {
		$payload = $this->request->getJsonBody();

		if(!is_array($payload)) {
			$payload = [];
		}

		$request = $this->normalizeRequest($payload);

		if($request['mode'] === 'detail') {
			return $this->buildDetailResponse($request['provider_key']);
		}

		if($request['mode'] === 'record') {
			return $this->buildRecordResponse($request['provider_key']);
		}

		if($request['mode'] === 'call_provider') {
			return $this->buildProviderCallResponse($request['provider_key'], $request);
		}

		return $this->buildPageResponse($request);
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function buildPageResponse(array $request): array {
		$rows = $this->buildRows();
		$rows = $this->applySearch($rows, $request['search']);
		$rows = $this->applySort($rows, $request['sort']);

		$total = count($rows);
		$pageSize = $request['pageSize'];
		$page = $request['page'];
		$totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 0;
		$offset = max(0, ($page - 1) * $pageSize);
		$pageRows = array_slice($rows, $offset, $pageSize);
		$data = [];

		foreach($pageRows as $row) {
			$data[] = [
				'id' => $row['id'],
				'provider_key' => $row['provider_key'],
				'name' => $row['name'],
				'topic' => $row['topic'],
				'title' => $row['title'],
				'class' => $row['class'],
				'description' => $row['description'],
				'priority' => $row['priority'],
				'aliases' => $row['aliases'],
				'alias_count' => $row['alias_count'],
				'supports_topic' => $row['supports_topic'],
			];
		}

		return [
			'mode' => 'page',
			'data' => $data,
			'groups' => [],
			'page' => $page,
			'pageSize' => $pageSize,
			'total' => $total,
			'totalPages' => $totalPages,
			'hasMore' => ($offset + $pageSize) < $total,
			'nextCursor' => null,
			'appliedSearch' => $request['search'],
			'appliedSort' => [$request['sort']],
			'appliedFilters' => [],
			'appliedGroup' => [],
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function normalizeRequest(array $payload): array {
		$mode = 'page';
		$allowedModes = ['page', 'detail', 'record', 'call_provider'];

		if(isset($payload['mode']) && is_string($payload['mode']) && in_array($payload['mode'], $allowedModes, true)) {
			$mode = $payload['mode'];
		}

		$page = isset($payload['page']) ? (int) $payload['page'] : 1;
		$page = max(1, $page);

		$pageSize = isset($payload['pageSize']) ? (int) $payload['pageSize'] : 50;
		$pageSize = max(1, min(200, $pageSize));

		$search = '';
		if(isset($payload['search']) && is_scalar($payload['search'])) {
			$search = trim((string) $payload['search']);
		}

		$providerKey = '';
		if(isset($payload['provider_key']) && is_scalar($payload['provider_key'])) {
			$providerKey = trim((string) $payload['provider_key']);
		}

		$topic = '';
		if(isset($payload['topic']) && is_scalar($payload['topic'])) {
			$topic = trim((string) $payload['topic']);
		}

		$scope = self::DEFAULT_SCOPE;
		if(isset($payload['scope']) && is_scalar($payload['scope'])) {
			$scope = $this->normalizeToken((string) $payload['scope']);
		}
		if(!in_array($scope, self::ALLOWED_SCOPES, true)) {
			$scope = self::DEFAULT_SCOPE;
		}

		$query = '';
		if(isset($payload['query']) && is_scalar($payload['query'])) {
			$query = trim((string) $payload['query']);
		}

		$limit = isset($payload['limit']) ? (int) $payload['limit'] : self::DEFAULT_LIMIT;
		$limit = max(1, min(self::MAX_LIMIT, $limit));

		$offset = isset($payload['offset']) ? (int) $payload['offset'] : 0;
		$offset = max(0, $offset);

		return [
			'mode' => $mode,
			'page' => $page,
			'pageSize' => $pageSize,
			'search' => $search,
			'sort' => $this->normalizeSort($payload['sort'] ?? null),
			'provider_key' => $providerKey,
			'topic' => $topic,
			'scope' => $scope,
			'query' => $query,
			'limit' => $limit,
			'offset' => $offset,
		];
	}

	/**
	 * @param mixed $sortPayload
	 * @return array<string, string>
	 */
	private function normalizeSort(mixed $sortPayload): array {
		$allowedKeys = [
			'name',
			'topic',
			'title',
			'class',
			'description',
			'priority',
			'aliases',
			'alias_count',
		];

		$sort = [
			'key' => 'priority',
			'dir' => 'desc',
			'type' => 'int',
		];

		if(!is_array($sortPayload) || count($sortPayload) === 0) {
			return $sort;
		}

		$first = reset($sortPayload);

		if(!is_array($first)) {
			return $sort;
		}

		$key = isset($first['key']) ? (string) $first['key'] : 'priority';
		if(!in_array($key, $allowedKeys, true)) {
			$key = 'priority';
		}

		$dir = isset($first['dir']) ? strtolower((string) $first['dir']) : 'desc';
		$dir = $dir === 'asc' ? 'asc' : 'desc';
		$type = in_array($key, ['priority', 'alias_count'], true) ? 'int' : 'string';

		return [
			'key' => $key,
			'dir' => $dir,
			'type' => $type,
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function buildRows(): array {
		$rows = [];
		$providers = $this->classmap->getInstancesByInterface(IAgentInfoTopicProvider::class);

		foreach($providers as $provider) {
			if(!$provider instanceof IAgentInfoTopicProvider) {
				continue;
			}

			$row = $this->buildRow($provider);

			if($row !== null) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function buildRow(IAgentInfoTopicProvider $provider): ?array {
		try {
			$name = $provider::getName();
			$topic = $this->normalizeToken($provider->getTopic());
			$aliases = $this->normalizeAliases($provider->getTopicAliases());
			$title = trim($provider->getTitle());
			$description = trim($provider->getDescription());
			$priority = $provider->getPriority();
			$supportsTopic = $this->safeSupports($provider, $topic);
			$meta = [
				'name' => $name,
				'topic' => $topic,
				'aliases' => $aliases,
				'title' => $title,
				'description' => $description,
				'priority' => $priority,
				'class' => $provider::class,
				'supports_topic' => $supportsTopic,
				'supports_aliases' => $this->buildAliasSupportMap($provider, $aliases),
				'supported_scopes' => self::ALLOWED_SCOPES,
			];

			return [
				'id' => $name,
				'provider_key' => $name,
				'name' => $name,
				'topic' => $topic,
				'title' => $title,
				'class' => $provider::class,
				'description' => $description,
				'priority' => $priority,
				'aliases' => implode(', ', $aliases),
				'alias_count' => count($aliases),
				'supports_topic' => $supportsTopic,
				'provider_meta' => $meta,
				'provider_meta_pretty' => $this->encodePrettyJson($meta),
				'provider_meta_json' => $this->encodeJson($meta),
			];
		}
		catch(\Throwable) {
			return null;
		}
	}

	/**
	 * @param array<int, string> $aliases
	 * @return array<string, bool>
	 */
	private function buildAliasSupportMap(IAgentInfoTopicProvider $provider, array $aliases): array {
		$out = [];

		foreach($aliases as $alias) {
			$out[$alias] = $this->safeSupports($provider, $alias);
		}

		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<int, array<string, mixed>>
	 */
	private function applySearch(array $rows, string $search): array {
		if($search === '') {
			return $rows;
		}

		$needle = $this->toLower($search);
		$result = [];

		foreach($rows as $row) {
			$haystack = implode("\n", [
				(string) ($row['provider_key'] ?? ''),
				(string) ($row['name'] ?? ''),
				(string) ($row['topic'] ?? ''),
				(string) ($row['title'] ?? ''),
				(string) ($row['class'] ?? ''),
				(string) ($row['description'] ?? ''),
				(string) ($row['aliases'] ?? ''),
				(string) ($row['provider_meta_json'] ?? ''),
			]);

			if(str_contains($this->toLower($haystack), $needle)) {
				$result[] = $row;
			}
		}

		return $result;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @param array<string, string> $sort
	 * @return array<int, array<string, mixed>>
	 */
	private function applySort(array $rows, array $sort): array {
		$key = $sort['key'] ?? 'priority';
		$dir = $sort['dir'] ?? 'desc';

		usort($rows, function(array $left, array $right) use ($key, $dir): int {
			$leftValue = $left[$key] ?? null;
			$rightValue = $right[$key] ?? null;

			if(in_array($key, ['priority', 'alias_count'], true)) {
				$result = ((int) $leftValue) <=> ((int) $rightValue);
			}
			else {
				$result = strcmp($this->toLower((string) $leftValue), $this->toLower((string) $rightValue));
			}

			if($result === 0) {
				$result = strcmp($this->toLower((string) ($left['topic'] ?? '')), $this->toLower((string) ($right['topic'] ?? '')));
			}

			if($result === 0) {
				$result = strcmp($this->toLower((string) ($left['name'] ?? '')), $this->toLower((string) ($right['name'] ?? '')));
			}

			return $dir === 'desc' ? -$result : $result;
		});

		return $rows;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildDetailResponse(string $providerKey): array {
		$row = $this->findRow($providerKey);

		if($row === null) {
			return [
				'mode' => 'detail',
				'found' => false,
				'detail' => null,
			];
		}

		return [
			'mode' => 'detail',
			'found' => true,
			'detail' => [
				'id' => $row['id'],
				'provider_key' => $row['provider_key'],
				'headline' => $row['title'] !== '' ? $row['title'] : $row['name'],
				'summary' => $row['class'],
				'description' => $row['description'],
				'name' => $row['name'],
				'topic' => $row['topic'],
				'aliases' => $row['aliases'],
				'priority' => $row['priority'],
				'supports_topic' => $row['supports_topic'],
				'badges' => [
					'Topic: ' . (string) $row['topic'],
					'Priority: ' . (string) $row['priority'],
					'Aliases: ' . (string) $row['alias_count'],
				],
				'supported_scopes' => self::ALLOWED_SCOPES,
				'default_request' => [
					'topic' => $row['topic'],
					'query' => '',
					'scope' => self::DEFAULT_SCOPE,
					'limit' => self::DEFAULT_LIMIT,
					'offset' => 0,
				],
				'provider_meta' => $row['provider_meta'],
				'provider_meta_json' => $row['provider_meta_pretty'],
			]
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildRecordResponse(string $providerKey): array {
		$row = $this->findRow($providerKey);

		return [
			'mode' => 'record',
			'found' => $row !== null,
			'record' => $row !== null ? $this->normalizeForJson($row) : null,
		];
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function buildProviderCallResponse(string $providerKey, array $request): array {
		if($providerKey === '') {
			return [
				'mode' => 'call_provider',
				'ok' => false,
				'error' => 'Missing provider_key.',
			];
		}

		$provider = $this->findProvider($providerKey);

		if(!$provider instanceof IAgentInfoTopicProvider) {
			return [
				'mode' => 'call_provider',
				'ok' => false,
				'provider_key' => $providerKey,
				'error' => 'Provider not found: ' . $providerKey,
			];
		}

		$infoRequest = $this->createInfoRequest($request, $provider);

		try {
			$result = $provider->handle($infoRequest);
			$resultPayload = $result instanceof AgentInfoResult ? $result->toArray(true) : $this->normalizeForJson($result);

			return [
				'mode' => 'call_provider',
				'ok' => true,
				'provider_key' => $providerKey,
				'provider' => $this->normalizeForJson($this->buildProviderMeta($provider)),
				'request' => $this->normalizeForJson($infoRequest),
				'supports_requested_topic' => $this->safeSupports($provider, $infoRequest->topic),
				'result' => $this->normalizeForJson($resultPayload),
			];
		}
		catch(\Throwable $e) {
			return [
				'mode' => 'call_provider',
				'ok' => false,
				'provider_key' => $providerKey,
				'provider' => $this->normalizeForJson($this->buildProviderMeta($provider)),
				'request' => $this->normalizeForJson($infoRequest),
				'error' => $e->getMessage(),
				'exception' => $e::class,
			];
		}
	}

	/**
	 * @param array<string, mixed> $request
	 */
	private function createInfoRequest(array $request, IAgentInfoTopicProvider $provider): AgentInfoRequest {
		$topic = trim((string) ($request['topic'] ?? ''));

		if($topic === '') {
			$topic = $provider->getTopic();
		}

		$topic = $this->normalizeToken($topic);
		$scope = $this->normalizeToken((string) ($request['scope'] ?? self::DEFAULT_SCOPE));

		if(!in_array($scope, self::ALLOWED_SCOPES, true)) {
			$scope = self::DEFAULT_SCOPE;
		}

		$query = trim((string) ($request['query'] ?? ''));
		$limit = max(1, min(self::MAX_LIMIT, (int) ($request['limit'] ?? self::DEFAULT_LIMIT)));
		$offset = max(0, (int) ($request['offset'] ?? 0));

		return new AgentInfoRequest(
			topic: $topic,
			query: $query,
			scope: $scope,
			limit: $limit,
			offset: $offset
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function findRow(string $providerKey): ?array {
		if($providerKey === '') {
			return null;
		}

		foreach($this->buildRows() as $row) {
			if((string) $row['provider_key'] === $providerKey || (string) $row['id'] === $providerKey || (string) $row['topic'] === $providerKey) {
				return $row;
			}
		}

		return null;
	}

	private function findProvider(string $providerKey): ?IAgentInfoTopicProvider {
		if($providerKey === '') {
			return null;
		}

		$provider = $this->classmap->getInstanceByInterfaceName(IAgentInfoTopicProvider::class, $providerKey);

		if($provider instanceof IAgentInfoTopicProvider) {
			return $provider;
		}

		$providers = $this->classmap->getInstancesByInterface(IAgentInfoTopicProvider::class);

		foreach($providers as $candidate) {
			if(!$candidate instanceof IAgentInfoTopicProvider) {
				continue;
			}

			if($candidate::getName() === $providerKey || $this->normalizeToken($candidate->getTopic()) === $providerKey) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildProviderMeta(IAgentInfoTopicProvider $provider): array {
		$topic = $this->normalizeToken($provider->getTopic());
		$aliases = $this->normalizeAliases($provider->getTopicAliases());

		return [
			'name' => $provider::getName(),
			'topic' => $topic,
			'aliases' => $aliases,
			'title' => $provider->getTitle(),
			'description' => $provider->getDescription(),
			'priority' => $provider->getPriority(),
			'class' => $provider::class,
			'supports_topic' => $this->safeSupports($provider, $topic),
			'supports_aliases' => $this->buildAliasSupportMap($provider, $aliases),
			'supported_scopes' => self::ALLOWED_SCOPES,
		];
	}

	private function safeSupports(IAgentInfoTopicProvider $provider, string $topic): bool {
		try {
			return $provider->supports($this->normalizeToken($topic));
		}
		catch(\Throwable) {
			return false;
		}
	}

	/**
	 * @param array<int, string> $aliases
	 * @return array<int, string>
	 */
	private function normalizeAliases(array $aliases): array {
		$out = [];

		foreach($aliases as $alias) {
			if(!is_scalar($alias)) {
				continue;
			}

			$alias = $this->normalizeToken((string) $alias);

			if($alias !== '') {
				$out[] = $alias;
			}
		}

		$out = array_values(array_unique($out));
		sort($out);

		return $out;
	}

	private function normalizeToken(string $value): string {
		$value = trim($this->toLower($value));
		return preg_replace('/\s+/u', '_', $value) ?? $value;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private function normalizeForJson(mixed $value, int $depth = 0): mixed {
		if($depth > 20) {
			return '[max-depth]';
		}

		if($value === null || is_scalar($value)) {
			return $value;
		}

		if($value instanceof AgentInfoRequest) {
			return [
				'topic' => $value->topic,
				'query' => $value->query,
				'scope' => $value->scope,
				'limit' => $value->limit,
				'offset' => $value->offset,
			];
		}

		if($value instanceof AgentInfoResult) {
			return $this->normalizeForJson($value->toArray(true), $depth + 1);
		}

		if(is_array($value)) {
			$out = [];

			foreach($value as $key => $item) {
				$out[$key] = $this->normalizeForJson($item, $depth + 1);
			}

			return $out;
		}

		if($value instanceof \JsonSerializable) {
			return $this->normalizeForJson($value->jsonSerialize(), $depth + 1);
		}

		if($value instanceof \stdClass) {
			return $this->normalizeForJson((array) $value, $depth + 1);
		}

		if(is_object($value)) {
			$out = [
				'__class' => $value::class,
			];

			if(method_exists($value, '__toString')) {
				$out['__string'] = (string) $value;
			}

			return $out;
		}

		if(is_resource($value)) {
			return '[resource]';
		}

		return '[unsupported]';
	}

	/**
	 * @param mixed $value
	 */
	private function encodeJson(mixed $value): string {
		$json = json_encode($this->normalizeForJson($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return is_string($json) ? $json : 'null';
	}

	/**
	 * @param mixed $value
	 */
	private function encodePrettyJson(mixed $value): string {
		$json = json_encode($this->normalizeForJson($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

		return is_string($json) ? $json : 'null';
	}

	private function toLower(string $value): string {
		if(function_exists('mb_strtolower')) {
			return mb_strtolower($value);
		}

		return strtolower($value);
	}
}
