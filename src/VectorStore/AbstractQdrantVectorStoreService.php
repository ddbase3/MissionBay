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

namespace MissionBay\VectorStore;

use AssistantFoundation\Api\IAiServiceTester;
use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use AssistantFoundation\Dto\RetrievalHit;
use AssistantFoundation\Dto\RetrievalIndexItem;
use AssistantFoundation\Dto\RetrievalSearchRequest;
use AssistantFoundation\Dto\RetrievalSearchResult;
use MissionBay\Api\IVectorStoreService;

abstract class AbstractQdrantVectorStoreService implements IVectorStoreService, IAiServiceTester {

	/** @var array<string,mixed> */
	protected array $options = [];

	protected ?string $baseUrl = null;
	protected ?string $authSecret = null;
	protected bool $createPayloadIndexes = false;

	/** @var array<string,bool> */
	private array $ensuredCollections = [];

	/** @var array<string,array<string,bool>> */
	private array $ensuredIndexes = [];

	public function __construct(
		protected readonly IRetrievalCollectionDefinition $collectionDefinition
	) {}

	abstract public static function getName(): string;

	public static function getType(): string {
		return 'qdrant';
	}

	abstract protected function buildUrl(string $path): string;

	/** @return array<int,string> */
	abstract protected function buildHeaders(): array;

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);

		$baseUrl = trim((string)($this->options['base_url'] ?? ''));
		$authType = strtolower(trim((string)($this->options['auth_type'] ?? 'api-key')));
		$authSecret = trim((string)($this->options['auth_secret'] ?? ''));

		if($baseUrl === '') {
			throw new \InvalidArgumentException(static::getName() . ': base_url is required.');
		}
		if($authType !== 'none' && $authSecret === '') {
			throw new \InvalidArgumentException(static::getName() . ': auth_secret is required.');
		}

		$this->baseUrl = $this->normalizeBaseUrl($baseUrl);
		$this->authSecret = $authSecret !== '' ? $authSecret : null;
		$this->createPayloadIndexes = $this->readBoolOption('create_payload_indexes', true);
		$this->ensuredCollections = [];
		$this->ensuredIndexes = [];
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function test(array $config): array {
		try {
			if($config !== []) {
				$this->setOptions($config);
			}

			$this->assertReady();
			$r = $this->curlJson('GET', $this->buildUrl('/collections'), null);
			$this->assertHttpSuccess($r, 'health test');

			$data = $this->decodeResponse($r);
			$collections = $data['result']['collections'] ?? [];

			return [
				'ok' => true,
				'message' => 'Qdrant responded successfully.',
				'details' => [
					'collectionCount' => is_array($collections) ? count($collections) : 0
				]
			];
		}
		catch(\Throwable $e) {
			return [
				'ok' => false,
				'message' => $e->getMessage(),
				'details' => []
			];
		}
	}

	public function upsert(RetrievalIndexItem $item): void {
		$this->assertReady();
		$this->collectionDefinition->validate($item);

		$collectionKey = trim($item->collectionKey);
		$this->ensureCollection($collectionKey);

		$collection = $this->collectionDefinition->getBackendCollectionName($collectionKey);
		$schema = $this->collectionDefinition->getIndexSchema($collectionKey);
		$vectors = $this->buildPointVectors($item, $schema);

		if($vectors === []) {
			throw new \RuntimeException(static::getName() . ': no retrieval representation was materialized for upsert.');
		}

		$body = [
			'points' => [[
				'id' => $this->buildPointId($item),
				'vector' => $vectors,
				'payload' => $this->collectionDefinition->buildPayload($item)
			]]
		];

		$r = $this->curlJson(
			'PUT',
			$this->buildUrl("/collections/{$collection}/points?wait=true"),
			$body
		);
		$this->assertHttpSuccess($r, 'upsert');
	}

	public function existsByHash(string $collectionKey, string $hash): bool {
		$hash = trim($hash);
		if($hash === '') {
			return false;
		}

		return $this->existsByFilter($collectionKey, ['hash' => $hash]);
	}

	public function existsByFilter(string $collectionKey, array $filter): bool {
		$this->assertReady();
		if(!$this->collectionExists($collectionKey)) {
			return false;
		}

		$collection = $this->collectionDefinition->getBackendCollectionName($collectionKey);
		$r = $this->curlJson('POST', $this->buildUrl("/collections/{$collection}/points/scroll"), [
			'filter' => $this->buildLifecycleFilter($filter),
			'limit' => 1,
			'with_payload' => false,
			'with_vector' => false
		]);
		$this->assertHttpSuccess($r, 'existsByFilter');

		$data = $this->decodeResponse($r);
		$points = $data['result']['points'] ?? [];
		return is_array($points) && $points !== [];
	}

	public function deleteByFilter(string $collectionKey, array $filter): int {
		$this->assertReady();
		if(!$this->collectionExists($collectionKey)) {
			return 0;
		}

		$collection = $this->collectionDefinition->getBackendCollectionName($collectionKey);
		$qdrantFilter = $this->buildLifecycleFilter($filter);
		$count = $this->countByFilter($collection, $qdrantFilter);

		$r = $this->curlJson('POST', $this->buildUrl("/collections/{$collection}/points/delete?wait=true"), [
			'filter' => $qdrantFilter
		]);
		$this->assertHttpSuccess($r, 'deleteByFilter');

		return $count;
	}

	public function search(RetrievalSearchRequest $request): RetrievalSearchResult {
		$this->assertReady();

		$collection = $this->collectionDefinition->getBackendCollectionName($request->collectionKey);
		$schema = $this->collectionDefinition->getIndexSchema($request->collectionKey);
		$filter = $this->buildSearchFilter($request, $schema);
		$channels = $this->buildSearchChannels($request, $schema);

		if($channels === []) {
			throw new \InvalidArgumentException('Retrieval search has no usable search channel.');
		}

		$body = $this->buildQueryBody($request, $channels, $filter);
		$r = $this->curlJson('POST', $this->buildUrl("/collections/{$collection}/points/query"), $body);
		$this->assertHttpSuccess($r, 'search');

		$data = $this->decodeResponse($r);
		$points = $data['result']['points'] ?? [];
		$hits = $this->buildHits($request->collectionKey, is_array($points) ? $points : []);

		return new RetrievalSearchResult(
			$hits,
			array_values(array_map(static fn(array $channel): string => $channel['channel'], $channels)),
			['mode' => $request->mode]
		);
	}

	public function context(
		string $collectionKey,
		string $pointId,
		int $before = 1,
		int $after = 1,
		?array $filterSpec = null
	): RetrievalSearchResult {
		$this->assertReady();
		$pointId = trim($pointId);
		$before = max(0, $before);
		$after = max(0, $after);

		if($pointId === '') {
			throw new \InvalidArgumentException('pointId is required.');
		}

		$collection = $this->collectionDefinition->getBackendCollectionName($collectionKey);
		$qdrantPointId = $this->normalizeQdrantPointId($pointId);
		$mandatory = $this->buildStructuredFilter($filterSpec);
		$pointFilter = $this->appendMustCondition($mandatory, ['has_id' => [$qdrantPointId]]);
		$point = $this->scrollOne($collection, $pointFilter);

		if($point === null) {
			return new RetrievalSearchResult([], ['context'], ['retrieval_ref' => $pointId]);
		}

		$contextSchema = $this->collectionDefinition->getContextSchema($collectionKey);
		$groupField = trim((string)($contextSchema['group_field'] ?? ''));
		$positionField = trim((string)($contextSchema['position_field'] ?? ''));
		$payload = is_array($point['payload'] ?? null) ? $point['payload'] : [];
		$groupValue = $payload[$groupField] ?? null;
		$position = $payload[$positionField] ?? null;

		if($groupField === '' || $positionField === '' || $groupValue === null || !is_numeric($position)) {
			throw new \RuntimeException('Collection definition does not provide usable context fields for the requested point.');
		}

		$position = (int)$position;
		$range = [
			'gte' => max(0, $position - $before),
			'lte' => $position + $after
		];
		$contextFilter = $this->appendMustCondition($mandatory, $this->buildCondition($groupField, 'eq', $groupValue));
		$contextFilter = $this->appendMustCondition($contextFilter, $this->buildCondition($positionField, 'range', $range));

		$r = $this->curlJson('POST', $this->buildUrl("/collections/{$collection}/points/scroll"), [
			'filter' => $contextFilter,
			'limit' => max(1, $before + $after + 1),
			'with_payload' => true,
			'with_vector' => false
		]);
		$this->assertHttpSuccess($r, 'context');

		$data = $this->decodeResponse($r);
		$points = $data['result']['points'] ?? [];
		$points = is_array($points) ? $points : [];

		usort($points, static function(array $a, array $b) use ($positionField): int {
			$ap = (int)(is_array($a['payload'] ?? null) ? ($a['payload'][$positionField] ?? 0) : 0);
			$bp = (int)(is_array($b['payload'] ?? null) ? ($b['payload'][$positionField] ?? 0) : 0);
			return $ap <=> $bp;
		});

		return new RetrievalSearchResult(
			$this->buildHits($collectionKey, $points, false),
			['context'],
			['retrieval_ref' => $pointId]
		);
	}

	public function createCollection(string $collectionKey): void {
		$this->assertReady();
		$collection = $this->collectionDefinition->getBackendCollectionName($collectionKey);
		$schema = $this->collectionDefinition->getIndexSchema($collectionKey);
		$body = $this->buildCollectionBody($schema);

		$r = $this->curlJson('PUT', $this->buildUrl("/collections/{$collection}"), $body);
		$this->assertHttpSuccess($r, 'createCollection');

		$this->ensuredCollections[strtolower($collection)] = true;
		if($this->createPayloadIndexes) {
			$this->createPayloadIndexes($collectionKey);
		}
	}

	public function deleteCollection(string $collectionKey): void {
		$this->assertReady();
		$collection = $this->collectionDefinition->getBackendCollectionName($collectionKey);
		$r = $this->curlJson('DELETE', $this->buildUrl("/collections/{$collection}"), null);
		$this->assertHttpSuccess($r, 'deleteCollection');

		unset($this->ensuredCollections[strtolower($collection)], $this->ensuredIndexes[strtolower($collection)]);
	}

	public function getInfo(string $collectionKey): array {
		$this->assertReady();
		$collection = $this->collectionDefinition->getBackendCollectionName($collectionKey);
		$r = $this->curlJson('GET', $this->buildUrl("/collections/{$collection}"), null);
		$this->assertHttpSuccess($r, 'getInfo');
		$data = $this->decodeResponse($r);

		return [
			'collection_key' => $collectionKey,
			'collection' => $collection,
			'index_schema' => $this->collectionDefinition->getIndexSchema($collectionKey),
			'expected_payload_schema' => $this->collectionDefinition->getPayloadSchema($collectionKey),
			'payload_schema' => $data['result']['payload_schema'] ?? [],
			'qdrant_raw' => $data
		];
	}

	public function inspectPoints(
		string $collectionKey,
		int $limit = 10,
		?array $filterSpec = null,
		string|int|null $offset = null,
		bool $withVectorSummary = false
	): array {
		$this->assertReady();

		$collection = $this->collectionDefinition->getBackendCollectionName($collectionKey);
		$limit = max(1, min(250, $limit));
		$body = [
			'limit' => $limit,
			'with_payload' => true,
			'with_vector' => $withVectorSummary
		];

		$filter = $this->buildStructuredFilter($filterSpec);
		if($filter !== null) {
			$body['filter'] = $filter;
		}
		if($offset !== null && $offset !== '') {
			$body['offset'] = $offset;
		}

		$r = $this->curlJson(
			'POST',
			$this->buildUrl("/collections/{$collection}/points/scroll"),
			$body
		);
		$this->assertHttpSuccess($r, 'inspectPoints');
		$data = $this->decodeResponse($r);
		$points = $data['result']['points'] ?? [];

		$out = [];
		if(is_array($points)) {
			foreach($points as $point) {
				if(!is_array($point)) continue;

				$id = $point['id'] ?? null;
				if(!is_string($id) && !is_int($id)) continue;

				$item = [
					'id' => $id,
					'payload' => is_array($point['payload'] ?? null) ? $point['payload'] : []
				];

				if($withVectorSummary) {
					$item['vectors'] = $this->summarizePointVectors($point['vector'] ?? null);
				}

				$out[] = $item;
			}
		}

		$nextOffset = $data['result']['next_page_offset'] ?? null;
		if(!is_string($nextOffset) && !is_int($nextOffset)) {
			$nextOffset = null;
		}

		return [
			'collection_key' => $collectionKey,
			'collection' => $collection,
			'points' => $out,
			'next_offset' => $nextOffset
		];
	}

	/** @return array<string,array<string,mixed>> */
	private function summarizePointVectors(mixed $vectors): array {
		if(!is_array($vectors)) {
			return [];
		}

		if(array_is_list($vectors)) {
			return ['default' => $this->summarizeVector($vectors)];
		}

		$out = [];
		foreach($vectors as $name => $vector) {
			if(!is_string($name) || trim($name) === '') continue;
			$out[$name] = $this->summarizeVector($vector);
		}

		return $out;
	}

	/** @return array<string,mixed> */
	private function summarizeVector(mixed $vector): array {
		if(is_array($vector) && is_array($vector['indices'] ?? null) && is_array($vector['values'] ?? null)) {
			return [
				'type' => 'sparse',
				'non_zero' => min(count($vector['indices']), count($vector['values']))
			];
		}

		if(is_array($vector)) {
			return [
				'type' => 'dense',
				'size' => count($vector)
			];
		}

		return ['type' => get_debug_type($vector)];
	}

	protected function normalizeBaseUrl(string $baseUrl): string {
		return rtrim(trim($baseUrl), '/');
	}

	protected function getBaseUrl(): string {
		return (string)$this->baseUrl;
	}

	protected function getAuthSecret(): string {
		return (string)$this->authSecret;
	}

	protected function getStringOption(string $key, string $default): string {
		$value = trim((string)($this->options[$key] ?? ''));
		return $value !== '' ? $value : $default;
	}

	protected function readBoolOption(string $key, bool $default): bool {
		if(!array_key_exists($key, $this->options)) {
			return $default;
		}

		$value = $this->options[$key];
		if(is_bool($value)) return $value;
		if(is_int($value)) return $value === 1;

		$value = strtolower(trim((string)$value));
		if(in_array($value, ['1', 'true', 'yes', 'on'], true)) return true;
		if(in_array($value, ['0', 'false', 'no', 'off'], true)) return false;
		return $default;
	}

	protected function readIntOption(string $key, int $default): int {
		$value = $this->options[$key] ?? null;
		if($value === null || $value === '' || !is_numeric($value)) {
			return $default;
		}

		$value = (int)$value;
		return $value >= 0 ? $value : $default;
	}

	/**
	 * @param array<string,array<string,mixed>> $schema
	 * @return array<string,mixed>
	 */
	private function buildCollectionBody(array $schema): array {
		$vectors = [];
		$sparseVectors = [];
		$dense = $schema['dense'] ?? null;

		if(is_array($dense)) {
			$name = trim((string)($dense['name'] ?? ''));
			$size = (int)($dense['size'] ?? 0);
			$distance = trim((string)($dense['distance'] ?? ''));
			if($name === '' || $size <= 0 || $distance === '') {
				throw new \RuntimeException('Dense retrieval schema requires name, size, and distance.');
			}
			$vectors[$name] = ['size' => $size, 'distance' => $distance];
		}

		foreach(['lexical', 'phonetic'] as $channel) {
			$definition = $schema[$channel] ?? null;
			if(!is_array($definition)) continue;

			$name = trim((string)($definition['name'] ?? ''));
			if($name === '') continue;

			$sparse = [];
			$modifier = strtolower(trim((string)($definition['modifier'] ?? '')));
			if($modifier !== '') {
				$sparse['modifier'] = $modifier;
			}
			$sparseVectors[$name] = $sparse;
		}

		$body = [];
		if($vectors !== []) $body['vectors'] = $vectors;
		if($sparseVectors !== []) $body['sparse_vectors'] = $sparseVectors;

		if($body === []) {
			throw new \RuntimeException('Retrieval collection has no configured vector representations.');
		}

		return $body;
	}

	/**
	 * @param array<string,array<string,mixed>> $schema
	 * @return array<string,mixed>
	 */
	private function buildPointVectors(RetrievalIndexItem $item, array $schema): array {
		$out = [];
		$dense = $schema['dense'] ?? null;
		if(is_array($dense) && $item->hasDenseVector()) {
			$name = trim((string)($dense['name'] ?? ''));
			$size = (int)($dense['size'] ?? 0);
			if($name !== '' && $size > 0 && count($item->denseVector) !== $size) {
				throw new \RuntimeException('Dense vector dimension mismatch: expected ' . $size . ', got ' . count($item->denseVector) . '.');
			}
			if($name !== '') {
				$out[$name] = $item->denseVector;
			}
		}

		foreach(['lexical', 'phonetic'] as $channel) {
			$definition = $schema[$channel] ?? null;
			if(!is_array($definition)) continue;

			$name = trim((string)($definition['name'] ?? ''));
			$model = trim((string)($definition['model'] ?? ''));
			$source = trim((string)($definition['source'] ?? ''));
			$text = $source === 'text' ? trim($item->text) : $item->getRepresentation($source);
			if($name === '' || $model === '' || $text === '') continue;

			$out[$name] = $this->buildInferenceDocument($text, $definition);
		}

		return $out;
	}

	/** @param array<string,mixed> $definition @return array<string,mixed> */
	private function buildInferenceDocument(string $text, array $definition): array {
		$model = trim((string)($definition['model'] ?? ''));
		if($model === '') {
			throw new \RuntimeException('Sparse retrieval schema requires a model.');
		}

		$document = [
			'text' => trim($text),
			'model' => $model
		];
		$options = $definition['options'] ?? null;
		if(is_array($options) && $options !== []) {
			$document['options'] = $options;
		}

		return $document;
	}

	/**
	 * @param array<string,array<string,mixed>> $schema
	 * @return array<int,array<string,mixed>>
	 */
	private function buildSearchChannels(RetrievalSearchRequest $request, array $schema): array {
		$mode = strtolower(trim($request->mode));
		$channels = [];

		$useDense = in_array($mode, [RetrievalSearchRequest::MODE_AUTO, RetrievalSearchRequest::MODE_HYBRID, RetrievalSearchRequest::MODE_SEMANTIC], true);
		$useLexical = in_array($mode, [RetrievalSearchRequest::MODE_AUTO, RetrievalSearchRequest::MODE_HYBRID, RetrievalSearchRequest::MODE_LEXICAL, RetrievalSearchRequest::MODE_EXACT], true);
		$usePhonetic = in_array($mode, [RetrievalSearchRequest::MODE_AUTO, RetrievalSearchRequest::MODE_HYBRID, RetrievalSearchRequest::MODE_PHONETIC], true);

		if($useDense && is_array($schema['dense'] ?? null)) {
			$name = trim((string)($schema['dense']['name'] ?? ''));
			if($name !== '') {
				if($request->denseVector === []) {
					if($mode === RetrievalSearchRequest::MODE_SEMANTIC) {
						throw new \InvalidArgumentException('Semantic retrieval requires a dense query vector.');
					}
				}
				else {
					$channels[] = ['channel' => 'semantic', 'using' => $name, 'query' => $request->denseVector];
				}
			}
		}

		if($useLexical && is_array($schema['lexical'] ?? null)) {
			$name = trim((string)($schema['lexical']['name'] ?? ''));
			$model = trim((string)($schema['lexical']['model'] ?? ''));
			if($name !== '' && $model !== '' && trim($request->query) !== '') {
				$channels[] = [
					'channel' => 'lexical',
					'using' => $name,
					'query' => $this->buildInferenceDocument($request->query, $schema['lexical'])
				];
			}
		}

		if($usePhonetic && is_array($schema['phonetic'] ?? null) && trim($request->phoneticText) !== '') {
			$name = trim((string)($schema['phonetic']['name'] ?? ''));
			$model = trim((string)($schema['phonetic']['model'] ?? ''));
			if($name !== '' && $model !== '') {
				$channels[] = [
					'channel' => 'phonetic',
					'using' => $name,
					'query' => $this->buildInferenceDocument($request->phoneticText, $schema['phonetic'])
				];
			}
		}

		return $channels;
	}

	/**
	 * @param array<int,array<string,mixed>> $channels
	 * @param array<string,mixed>|null $filter
	 * @return array<string,mixed>
	 */
	private function buildQueryBody(RetrievalSearchRequest $request, array $channels, ?array $filter): array {
		$limit = max(1, $request->limit);
		$candidateLimit = max($limit, $request->candidateLimit);

		if(count($channels) === 1) {
			$channel = $channels[0];
			$body = [
				'query' => $channel['query'],
				'using' => $channel['using'],
				'limit' => $limit,
				'with_payload' => true,
				'with_vector' => false
			];
			if($filter !== null) $body['filter'] = $filter;
			if($channel['channel'] === 'semantic' && $request->denseMinScore !== null) {
				$body['score_threshold'] = $request->denseMinScore;
			}
			return $body;
		}

		$prefetch = [];
		foreach($channels as $channel) {
			$item = [
				'query' => $channel['query'],
				'using' => $channel['using'],
				'limit' => $candidateLimit
			];
			if($filter !== null) $item['filter'] = $filter;
			$prefetch[] = $item;
		}

		return [
			'prefetch' => $prefetch,
			'query' => ['fusion' => 'rrf'],
			'limit' => $limit,
			'with_payload' => true,
			'with_vector' => false
		];
	}

	/**
	 * @param array<string,array<string,mixed>> $schema
	 */
	private function buildSearchFilter(RetrievalSearchRequest $request, array $schema): ?array {
		$filter = $this->buildStructuredFilter($request->filterSpec);
		$phraseField = trim((string)($schema['phrase']['field'] ?? ''));
		$phoneticPhraseField = trim((string)($schema['phonetic_phrase']['field'] ?? ''));

		foreach($request->phrases as $phrase) {
			$phrase = trim((string)$phrase);
			if($phrase !== '' && $phraseField !== '') {
				$filter = $this->appendMustCondition($filter, $this->buildCondition($phraseField, 'phrase', $phrase));
			}
		}

		foreach($request->phoneticPhrases as $phrase) {
			$phrase = trim((string)$phrase);
			if($phrase !== '' && $phoneticPhraseField !== '') {
				$filter = $this->appendMustCondition($filter, $this->buildCondition($phoneticPhraseField, 'phrase', $phrase));
			}
		}

		foreach($request->requiredTerms as $term) {
			$term = trim((string)$term);
			if($term !== '' && $phraseField !== '') {
				$filter = $this->appendMustCondition($filter, $this->buildCondition($phraseField, 'text', $term));
			}
		}

		foreach($request->excludedTerms as $term) {
			$term = trim((string)$term);
			if($term !== '' && $phraseField !== '') {
				$filter = $this->appendMustNotCondition($filter, $this->buildCondition($phraseField, 'text', $term));
			}
		}

		if($request->mode === RetrievalSearchRequest::MODE_EXACT && trim($request->query) !== '' && $phraseField !== '') {
			$filter = $this->appendMustCondition($filter, $this->buildCondition($phraseField, 'phrase', trim($request->query)));
		}

		return $filter;
	}

	private function buildStructuredFilter(?array $spec): ?array {
		if($spec === null || $spec === []) {
			return null;
		}

		$unknownGroups = array_diff(array_keys($spec), ['must', 'should', 'must_not']);
		if($unknownGroups !== []) {
			throw new \InvalidArgumentException('Unsupported retrieval filter group: ' . (string)reset($unknownGroups));
		}

		$out = [];
		foreach(['must', 'should', 'must_not'] as $group) {
			$conditions = $spec[$group] ?? [];
			if(!is_array($conditions)) {
				throw new \InvalidArgumentException("Retrieval filter group '{$group}' must be an array.");
			}

			foreach($conditions as $condition) {
				if(!is_array($condition)) {
					throw new \InvalidArgumentException("Retrieval filter group '{$group}' contains a non-object condition.");
				}

				$field = trim((string)($condition['field'] ?? ''));
				$operator = strtolower(trim((string)($condition['operator'] ?? '')));
				if($field === '' || $operator === '') {
					throw new \InvalidArgumentException("Retrieval filter group '{$group}' contains a condition without field/operator.");
				}

				$out[$group][] = $this->buildCondition($field, $operator, $condition['value'] ?? null);
			}
		}

		return $out === [] ? null : $out;
	}

	private function normalizeQdrantPointId(string $pointId): string|int {
		$pointId = trim($pointId);
		if(preg_match('/^[0-9]+$/', $pointId) === 1) {
			if(strlen($pointId) > strlen((string)PHP_INT_MAX)
				|| (strlen($pointId) === strlen((string)PHP_INT_MAX) && strcmp($pointId, (string)PHP_INT_MAX) > 0)) {
				throw new \InvalidArgumentException('Qdrant numeric point id exceeds the supported PHP integer range.');
			}

			return (int)$pointId;
		}

		if(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $pointId) === 1) {
			return $pointId;
		}

		throw new \InvalidArgumentException(
			'Qdrant context requires the exact retrieval_ref returned by retrieval_search (UUID or unsigned integer point id).'
		);
	}

	/** @return array<string,mixed> */
	private function buildCondition(string $field, string $operator, mixed $value): array {
		return match($operator) {
			'eq' => ['key' => $field, 'match' => ['value' => $value]],
			'in' => ['key' => $field, 'match' => ['any' => $this->normalizeScalarArray($value)]],
			'range' => ['key' => $field, 'range' => $this->normalizeRange($value)],
			'text' => ['key' => $field, 'match' => ['text' => trim((string)$value)]],
			'phrase' => ['key' => $field, 'match' => ['phrase' => trim((string)$value)]],
			default => throw new \InvalidArgumentException("Unsupported retrieval filter operator '{$operator}'.")
		};
	}

	private function appendMustCondition(?array $filter, array $condition): array {
		$filter ??= [];
		$filter['must'] ??= [];
		$filter['must'][] = $condition;
		return $filter;
	}

	private function appendMustNotCondition(?array $filter, array $condition): array {
		$filter ??= [];
		$filter['must_not'] ??= [];
		$filter['must_not'][] = $condition;
		return $filter;
	}

	/** @return array<int,mixed> */
	private function normalizeScalarArray(mixed $value): array {
		if(!is_array($value)) {
			$value = [$value];
		}

		$out = [];
		foreach($value as $item) {
			if(is_string($item) || is_int($item) || is_float($item) || is_bool($item)) {
				$out[] = $item;
			}
		}

		$out = array_values(array_unique($out, SORT_REGULAR));
		if($out === []) {
			throw new \InvalidArgumentException('Retrieval in-filter requires at least one scalar value.');
		}
		return $out;
	}

	/** @return array<string,int|float|string> */
	private function normalizeRange(mixed $value): array {
		if(!is_array($value)) {
			throw new \InvalidArgumentException('Retrieval range filter requires an object value.');
		}

		$out = [];
		foreach(['gt', 'gte', 'lt', 'lte'] as $key) {
			if(!array_key_exists($key, $value)) continue;

			$item = $value[$key];
			if(is_int($item) || is_float($item)) {
				$out[$key] = $item;
				continue;
			}
			if(is_string($item) && trim($item) !== '') {
				$out[$key] = is_numeric($item) ? $item + 0 : trim($item);
			}
		}
		if($out === []) {
			throw new \InvalidArgumentException('Retrieval range filter requires gt, gte, lt, or lte.');
		}
		return $out;
	}

	/** @param array<string,mixed> $filter */
	private function buildLifecycleFilter(array $filter): array {
		$must = [];
		foreach($filter as $key => $value) {
			$key = trim((string)$key);
			if($key === '') continue;

			if(is_array($value)) {
				$must[] = ['key' => $key, 'match' => ['any' => $this->normalizeScalarArray($value)]];
			}
			else {
				$must[] = ['key' => $key, 'match' => ['value' => $value]];
			}
		}
		return ['must' => $must];
	}

	private function ensureCollection(string $collectionKey): void {
		$collectionKey = trim($collectionKey);
		if($collectionKey === '') {
			throw new \InvalidArgumentException(static::getName() . ': collectionKey is required.');
		}

		$collection = $this->collectionDefinition->getBackendCollectionName($collectionKey);
		$cacheKey = strtolower(trim($collection));
		if(isset($this->ensuredCollections[$cacheKey])) {
			return;
		}

		$r = $this->curlJson('GET', $this->buildUrl("/collections/{$collection}"), null);
		$http = (int)($r['http'] ?? 0);
		if($http === 404) {
			$this->createCollection($collectionKey);
			return;
		}
		$this->assertHttpSuccess($r, 'ensureCollection');

		$this->validateExistingCollection($collectionKey, $this->decodeResponse($r));
		$this->ensuredCollections[$cacheKey] = true;
		if($this->createPayloadIndexes) {
			$this->createPayloadIndexes($collectionKey);
		}
	}

	private function collectionExists(string $collectionKey): bool {
		$collection = $this->collectionDefinition->getBackendCollectionName($collectionKey);
		$r = $this->curlJson('GET', $this->buildUrl("/collections/{$collection}"), null);
		$http = (int)($r['http'] ?? 0);
		if($http === 404) return false;
		$this->assertHttpSuccess($r, 'collection lookup');
		return true;
	}

	/** @param array<string,mixed> $data */
	private function validateExistingCollection(string $collectionKey, array $data): void {
		$schema = $this->collectionDefinition->getIndexSchema($collectionKey);
		$params = $data['result']['config']['params'] ?? [];
		$vectors = is_array($params['vectors'] ?? null) ? $params['vectors'] : [];
		$sparse = is_array($params['sparse_vectors'] ?? null) ? $params['sparse_vectors'] : [];

		$dense = $schema['dense'] ?? null;
		if(is_array($dense)) {
			$name = trim((string)($dense['name'] ?? ''));
			$current = is_array($vectors[$name] ?? null) ? $vectors[$name] : null;
			if($current === null || (int)($current['size'] ?? 0) !== (int)($dense['size'] ?? 0) || strcasecmp((string)($current['distance'] ?? ''), (string)($dense['distance'] ?? '')) !== 0) {
				throw new \RuntimeException("Existing Qdrant collection does not match dense retrieval schema '{$name}'. Recreate the collection.");
			}
		}

		foreach(['lexical', 'phonetic'] as $channel) {
			$definition = $schema[$channel] ?? null;
			if(!is_array($definition)) continue;
			$name = trim((string)($definition['name'] ?? ''));
			if($name !== '' && !array_key_exists($name, $sparse)) {
				throw new \RuntimeException("Existing Qdrant collection is missing sparse retrieval vector '{$name}'. Recreate the collection.");
			}
		}
	}

	private function createPayloadIndexes(string $collectionKey): void {
		$schema = $this->collectionDefinition->getPayloadSchema($collectionKey);
		foreach($schema as $field => $definition) {
			if(!is_array($definition) || ($definition['index'] ?? false) !== true) continue;
			$this->ensureIndex($collectionKey, (string)$field, $this->buildFieldSchema($definition));
		}
	}

	/** @param array<string,mixed> $definition */
	private function buildFieldSchema(array $definition): string|array {
		$type = strtolower(trim((string)($definition['type'] ?? '')));
		if(!in_array($type, ['keyword', 'integer', 'float', 'bool', 'text', 'uuid', 'datetime', 'geo'], true)) {
			throw new \RuntimeException("Unsupported Qdrant payload index type '{$type}'.");
		}

		$params = $definition['params'] ?? null;
		if($type !== 'text' || !is_array($params) || $params === []) {
			return $type;
		}

		return array_merge(['type' => 'text'], $params);
	}

	private function ensureIndex(string $collectionKey, string $field, string|array $fieldSchema): void {
		$collection = $this->collectionDefinition->getBackendCollectionName($collectionKey);
		$cacheKey = strtolower($collection);
		if(isset($this->ensuredIndexes[$cacheKey][$field])) return;

		$r = $this->curlJson('PUT', $this->buildUrl("/collections/{$collection}/index?wait=true"), [
			'field_name' => $field,
			'field_schema' => $fieldSchema
		]);
		$http = (int)($r['http'] ?? 0);
		$raw = (string)($r['raw'] ?? '');

		if(($http >= 200 && $http < 300) || $http === 409 || stripos($raw, 'already exists') !== false) {
			$this->ensuredIndexes[$cacheKey][$field] = true;
			return;
		}

		throw new \RuntimeException(static::getName() . " ensureIndex '{$field}' failed HTTP {$http}: " . ($r['error'] ?? '') . ' ' . $raw);
	}

	/** @param array<string,mixed> $filter */
	private function countByFilter(string $collection, array $filter): int {
		$r = $this->curlJson('POST', $this->buildUrl("/collections/{$collection}/points/count"), [
			'filter' => $filter,
			'exact' => true
		]);
		$this->assertHttpSuccess($r, 'countByFilter');
		$data = $this->decodeResponse($r);
		$count = $data['result']['count'] ?? 0;
		return is_numeric($count) ? (int)$count : 0;
	}

	/** @param array<string,mixed>|null $filter */
	private function scrollOne(string $collection, ?array $filter): ?array {
		$body = [
			'limit' => 1,
			'with_payload' => true,
			'with_vector' => false
		];
		if($filter !== null) $body['filter'] = $filter;

		$r = $this->curlJson('POST', $this->buildUrl("/collections/{$collection}/points/scroll"), $body);
		$this->assertHttpSuccess($r, 'context point lookup');
		$data = $this->decodeResponse($r);
		$points = $data['result']['points'] ?? [];
		return is_array($points) && isset($points[0]) && is_array($points[0]) ? $points[0] : null;
	}

	/**
	 * @param array<int,array<string,mixed>> $points
	 * @return RetrievalHit[]
	 */
	private function buildHits(string $collectionKey, array $points, bool $requireScore = true): array {
		$out = [];
		foreach($points as $point) {
			if(!is_array($point)) continue;
			$id = $point['id'] ?? null;
			if(!is_string($id) && !is_int($id)) continue;

			$score = $point['score'] ?? null;
			if($requireScore && !is_numeric($score)) continue;
			$score = is_numeric($score) ? (float)$score : null;
			$payload = is_array($point['payload'] ?? null) ? $point['payload'] : [];

			$out[] = new RetrievalHit(
				(string)$id,
				$score,
				$this->collectionDefinition->projectPayload($collectionKey, $payload)
			);
		}
		return $out;
	}

	private function buildPointId(RetrievalIndexItem $item): string {
		$contextSchema = $this->collectionDefinition->getContextSchema($item->collectionKey);
		$groupField = trim((string)($contextSchema['group_field'] ?? ''));
		$groupValue = $groupField !== '' ? ($item->metadata[$groupField] ?? null) : null;
		if(is_string($groupValue) || is_int($groupValue) || is_float($groupValue) || is_bool($groupValue)) {
			$groupValue = trim((string)$groupValue);
			if($groupValue !== '') {
				$base = $item->collectionKey . ':' . $groupValue . ':' . $item->chunkIndex;
				return $this->uuidV5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', $base);
			}
		}

		$hash = trim($item->hash);
		if($hash !== '') {
			return $this->uuidV5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', $item->collectionKey . ':' . $hash . ':' . $item->chunkIndex);
		}

		return $this->generateUuid();
	}

	private function uuidV5(string $namespaceUuid, string $name): string {
		$nsHex = str_replace('-', '', strtolower(trim($namespaceUuid)));
		if(strlen($nsHex) !== 32 || !ctype_xdigit($nsHex)) {
			throw new \InvalidArgumentException('uuidV5: invalid namespace UUID.');
		}

		$nsBin = hex2bin($nsHex);
		if($nsBin === false) {
			throw new \InvalidArgumentException('uuidV5: cannot decode namespace UUID.');
		}

		$hash = sha1($nsBin . $name);
		$timeHiVal = (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000;
		$clkSeqVal = (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000;

		return sprintf(
			'%s-%s-%04x-%04x-%s',
			substr($hash, 0, 8),
			substr($hash, 8, 4),
			$timeHiVal,
			$clkSeqVal,
			substr($hash, 20, 12)
		);
	}

	protected function generateUuid(): string {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff)
		);
	}

	protected function curlJson(string $method, string $url, ?array $body): array {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders());

		$timeout = $this->readIntOption('timeout_seconds', 90);
		$connectTimeout = $this->readIntOption('connect_timeout_seconds', 20);
		if($timeout > 0) curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		if($connectTimeout > 0) curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);

		if($method === 'POST') curl_setopt($ch, CURLOPT_POST, true);
		elseif($method !== 'GET') curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

		if($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
		}

		$raw = curl_exec($ch);
		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		return ['raw' => $raw, 'http' => $http, 'error' => $error];
	}

	/** @param array<string,mixed> $response */
	private function assertHttpSuccess(array $response, string $operation): void {
		$http = (int)($response['http'] ?? 0);
		if($http >= 200 && $http < 300) return;

		throw new \RuntimeException(
			static::getName() . " {$operation} failed HTTP {$http}: " .
			(string)($response['error'] ?? '') . ' ' . (string)($response['raw'] ?? '')
		);
	}

	/** @param array<string,mixed> $response @return array<string,mixed> */
	private function decodeResponse(array $response): array {
		$data = json_decode((string)($response['raw'] ?? ''), true);
		return is_array($data) ? $data : [];
	}

	private function assertReady(): void {
		if(!$this->baseUrl || trim($this->baseUrl) === '') {
			throw new \RuntimeException(static::getName() . ' not configured: base URL missing.');
		}

		$authType = strtolower($this->getStringOption('auth_type', 'api-key'));
		if($authType !== 'none' && (!$this->authSecret || trim($this->authSecret) === '')) {
			throw new \RuntimeException(static::getName() . ' not configured: connection auth secret missing.');
		}
	}
}
