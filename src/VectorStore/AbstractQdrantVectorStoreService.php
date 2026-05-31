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

use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\Api\IVectorStoreService;
use MissionBay\Dto\AgentEmbeddingChunk;

abstract class AbstractQdrantVectorStoreService implements IVectorStoreService {

	/**
	 * @var array<string,mixed>
	 */
	protected array $options = [];

	protected ?string $baseUrl = null;
	protected ?string $authSecret = null;
	protected bool $createPayloadIndexes = false;

	/** @var array<string,bool> cache by backend collection name */
	private array $ensuredCollections = [];

	/** @var array<string,array<string,bool>> cache by backend collection name */
	private array $ensuredIndexes = [];

	public function __construct(
		protected readonly IAgentRagPayloadNormalizer $normalizer
	) {}

	abstract public static function getName(): string;

	abstract protected function buildUrl(string $path): string;

	/**
	 * @return array<int,string>
	 */
	abstract protected function buildHeaders(): array;

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);

		$baseUrl = trim((string)($this->options['base_url'] ?? ''));
		$authSecret = trim((string)($this->options['auth_secret'] ?? ''));

		if($baseUrl === '') {
			throw new \InvalidArgumentException(static::getName() . ': base_url is required.');
		}

		if($authSecret === '') {
			throw new \InvalidArgumentException(static::getName() . ': auth_secret is required.');
		}

		$this->baseUrl = $this->normalizeBaseUrl($baseUrl);
		$this->authSecret = $authSecret;
		$this->createPayloadIndexes = $this->readBoolOption('create_payload_indexes', true);
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function upsert(AgentEmbeddingChunk $chunk): void {
		$this->assertReady();
		$this->normalizer->validate($chunk);

		$collectionKey = (string)$chunk->collectionKey;
		$this->ensureCollection($collectionKey);

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$url = $this->buildUrl("/collections/{$collection}/points?wait=true");
		$payload = $this->normalizer->buildPayload($chunk);
		$pointId = $this->buildPointId($chunk);

		$body = [
			'points' => [
				[
					'id' => $pointId,
					'vector' => $chunk->vector,
					'payload' => $payload
				]
			]
		];

		$r = $this->curlJson('PUT', $url, $body);
		$http = (int)($r['http'] ?? 0);

		if($http < 200 || $http >= 300) {
			throw new \RuntimeException(static::getName() . " upsert failed HTTP {$http}: " . ($r['error'] ?? '') . ' ' . ($r['raw'] ?? ''));
		}
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
		$this->ensureCollection($collectionKey);

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$url = $this->buildUrl("/collections/{$collection}/points/scroll");
		$body = [
			'filter' => $this->buildQdrantFilter($filter),
			'limit' => 1,
			'with_payload' => false,
			'with_vector' => false
		];

		$r = $this->curlJson('POST', $url, $body);
		$http = (int)($r['http'] ?? 0);

		if($http < 200 || $http >= 300) {
			throw new \RuntimeException(static::getName() . " existsByFilter failed HTTP {$http}: " . ($r['error'] ?? '') . ' ' . ($r['raw'] ?? ''));
		}

		$data = json_decode((string)($r['raw'] ?? ''), true);

		return isset($data['result']['points']) && is_array($data['result']['points']) && count($data['result']['points']) > 0;
	}

	public function deleteByFilter(string $collectionKey, array $filter): int {
		$this->assertReady();
		$this->ensureCollection($collectionKey);

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$url = $this->buildUrl("/collections/{$collection}/points/delete?wait=true");
		$body = [
			'filter' => $this->buildQdrantFilter($filter)
		];

		$r = $this->curlJson('POST', $url, $body);
		$http = (int)($r['http'] ?? 0);

		if($http < 200 || $http >= 300) {
			throw new \RuntimeException(static::getName() . " deleteByFilter failed HTTP {$http}: " . ($r['error'] ?? '') . ' ' . ($r['raw'] ?? ''));
		}

		$data = json_decode((string)($r['raw'] ?? ''), true);
		$deleted = $data['result']['deleted'] ?? null;

		if(is_int($deleted)) {
			return $deleted;
		}

		$points = $data['result']['points'] ?? null;

		if(is_array($points)) {
			return count($points);
		}

		return 0;
	}

	public function search(string $collectionKey, array $vector, int $limit = 3, ?float $minScore = null, ?array $filterSpec = null): array {
		$this->assertReady();
		$this->ensureCollection($collectionKey);

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$url = $this->buildUrl("/collections/{$collection}/points/search");
		$body = [
			'vector' => $vector,
			'limit' => $limit,
			'with_payload' => true,
			'with_vector' => false
		];

		$qdrantFilter = $this->buildQdrantFilterFromSpec($filterSpec);

		if($qdrantFilter !== null) {
			$body['filter'] = $qdrantFilter;
		}

		$r = $this->curlJson('POST', $url, $body);
		$http = (int)($r['http'] ?? 0);

		if($http < 200 || $http >= 300) {
			throw new \RuntimeException(static::getName() . " search failed HTTP {$http}: " . ($r['error'] ?? '') . ' ' . ($r['raw'] ?? ''));
		}

		$data = json_decode((string)($r['raw'] ?? ''), true);

		if(!isset($data['result']) || !is_array($data['result'])) {
			return [];
		}

		$out = [];

		foreach($data['result'] as $hit) {
			$score = $hit['score'] ?? null;

			if(!is_numeric($score)) {
				continue;
			}

			$score = (float)$score;

			if($minScore !== null && $score < $minScore) {
				continue;
			}

			$out[] = [
				'id' => $hit['id'] ?? null,
				'score' => $score,
				'payload' => $hit['payload'] ?? []
			];
		}

		return $out;
	}

	public function createCollection(string $collectionKey): void {
		$this->assertReady();

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$vectorSize = $this->normalizer->getVectorSize($collectionKey);
		$distance = $this->normalizer->getDistance($collectionKey);
		$url = $this->buildUrl("/collections/{$collection}");
		$body = [
			'vectors' => [
				'size' => $vectorSize,
				'distance' => $distance
			]
		];

		$r = $this->curlJson('PUT', $url, $body);
		$http = (int)($r['http'] ?? 0);

		if($http < 200 || $http >= 300) {
			throw new \RuntimeException(static::getName() . " createCollection HTTP {$http}: " . ($r['error'] ?? '') . ' ' . ($r['raw'] ?? ''));
		}

		if($this->createPayloadIndexes) {
			$this->createPayloadIndexes($collectionKey);
		}
	}

	public function deleteCollection(string $collectionKey): void {
		$this->assertReady();

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$url = $this->buildUrl("/collections/{$collection}");
		$r = $this->curlJson('DELETE', $url, null);
		$http = (int)($r['http'] ?? 0);

		if($http < 200 || $http >= 300) {
			throw new \RuntimeException(static::getName() . " deleteCollection HTTP {$http}: " . ($r['raw'] ?? ''));
		}
	}

	public function getInfo(string $collectionKey): array {
		$this->assertReady();

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$url = $this->buildUrl("/collections/{$collection}");
		$r = $this->curlJson('GET', $url, null);
		$data = json_decode((string)($r['raw'] ?? ''), true);

		return [
			'collection_key' => $collectionKey,
			'collection' => $collection,
			'vector_size' => $this->normalizer->getVectorSize($collectionKey),
			'distance' => $this->normalizer->getDistance($collectionKey),
			'payload_schema' => $data['result']['payload_schema'] ?? [],
			'qdrant_raw' => $data
		];
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

		if(is_bool($value)) {
			return $value;
		}

		if(is_int($value)) {
			return $value === 1;
		}

		$value = strtolower(trim((string)$value));

		if(in_array($value, ['1', 'true', 'yes', 'on'], true)) {
			return true;
		}

		if(in_array($value, ['0', 'false', 'no', 'off'], true)) {
			return false;
		}

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

	protected function createPayloadIndexes(string $collectionKey): void {
		$schema = $this->normalizer->getSchema($collectionKey);

		if(empty($schema) || !is_array($schema)) {
			return;
		}

		foreach($schema as $field => $def) {
			$type = $this->extractIndexTypeFromSchemaDef($def);

			if($type === null) {
				continue;
			}

			$index = $this->extractIndexFlagFromSchemaDef($def);

			if(!$index) {
				continue;
			}

			$this->ensureIndex($collectionKey, (string)$field, $type);
		}
	}

	private function extractIndexFlagFromSchemaDef(mixed $def): bool {
		if(!is_array($def)) {
			return false;
		}

		$flag = $def['index'] ?? false;

		return $flag === true;
	}

	protected function buildQdrantFilter(array $filter): array {
		$must = [];

		foreach($filter as $key => $value) {
			$key = trim((string)$key);

			if($key === '') {
				continue;
			}

			if(is_array($value)) {
				$vals = [];

				foreach($value as $v) {
					if($v === null || is_array($v) || is_object($v)) {
						continue;
					}

					$vals[] = $v;
				}

				$vals = array_values(array_unique($vals, SORT_REGULAR));

				if($vals) {
					$must[] = [
						'key' => $key,
						'match' => [
							'any' => $vals
						]
					];
				}

				continue;
			}

			$must[] = [
				'key' => $key,
				'match' => [
					'value' => $value
				]
			];
		}

		return [
			'must' => $must
		];
	}

	private function buildQdrantFilterFromSpec(?array $spec): ?array {
		if($spec === null || empty($spec)) {
			return null;
		}

		$must = $this->buildQdrantConditionsFromMap($spec['must'] ?? null);
		$should = $this->buildQdrantConditionsFromMap($spec['any'] ?? null);
		$mustNot = $this->buildQdrantConditionsFromMap($spec['must_not'] ?? null);
		$out = [];

		if(!empty($must)) {
			$out['must'] = $must;
		}

		if(!empty($should)) {
			$out['should'] = $should;
		}

		if(!empty($mustNot)) {
			$out['must_not'] = $mustNot;
		}

		return empty($out) ? null : $out;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function buildQdrantConditionsFromMap(mixed $map): array {
		if($map === null || !is_array($map)) {
			return [];
		}

		$out = [];

		foreach($map as $key => $value) {
			$key = trim((string)$key);

			if($key === '') {
				continue;
			}

			if(is_array($value)) {
				$vals = [];

				foreach($value as $v) {
					if($v === null || is_array($v) || is_object($v)) {
						continue;
					}

					$vals[] = $v;
				}

				$vals = array_values(array_unique($vals, SORT_REGULAR));

				if(!$vals) {
					continue;
				}

				$out[] = [
					'key' => $key,
					'match' => [
						'any' => $vals
					]
				];

				continue;
			}

			$out[] = [
				'key' => $key,
				'match' => [
					'value' => $value
				]
			];
		}

		return $out;
	}

	private function ensureCollection(string $collectionKey): void {
		$collectionKey = trim($collectionKey);

		if($collectionKey === '') {
			throw new \InvalidArgumentException(static::getName() . ': collectionKey is required.');
		}

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$cacheKey = strtolower(trim($collection));

		if($cacheKey === '') {
			throw new \RuntimeException(static::getName() . ': backend collection name is empty.');
		}

		if(isset($this->ensuredCollections[$cacheKey])) {
			return;
		}

		$url = $this->buildUrl("/collections/{$collection}");
		$r = $this->curlJson('GET', $url, null);
		$http = (int)($r['http'] ?? 0);

		if($http === 404) {
			$this->createCollection($collectionKey);
		}
		elseif($http < 200 || $http >= 300) {
			throw new \RuntimeException(static::getName() . " ensureCollection HTTP {$http}: " . ($r['raw'] ?? ''));
		}

		$this->ensuredCollections[$cacheKey] = true;

		if($this->createPayloadIndexes) {
			$this->createPayloadIndexes($collectionKey);
		}
	}

	private function ensureIndex(string $collectionKey, string $field, string $type): void {
		$collectionKey = trim($collectionKey);
		$field = trim($field);
		$type = trim($type);

		if($collectionKey === '' || $field === '' || $type === '') {
			return;
		}

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$cacheKey = strtolower(trim($collection));

		if($cacheKey === '') {
			return;
		}

		if(isset($this->ensuredIndexes[$cacheKey][$field])) {
			return;
		}

		$url = $this->buildUrl("/collections/{$collection}/index");
		$body = [
			'field_name' => $field,
			'field_schema' => $type
		];

		$r = $this->curlJson('PUT', $url, $body);
		$http = (int)($r['http'] ?? 0);
		$raw = (string)($r['raw'] ?? '');

		if($http >= 200 && $http < 300) {
			$this->ensuredIndexes[$cacheKey][$field] = true;
			return;
		}

		if($http === 409 || stripos($raw, 'already exists') !== false) {
			$this->ensuredIndexes[$cacheKey][$field] = true;
			return;
		}

		throw new \RuntimeException(static::getName() . " ensureIndex '{$field}' failed HTTP {$http}: " . ($r['error'] ?? '') . ' ' . $raw);
	}

	private function extractIndexTypeFromSchemaDef(mixed $def): ?string {
		if(!is_array($def)) {
			return null;
		}

		$type = $def['type'] ?? null;

		if(!is_string($type)) {
			return null;
		}

		$type = strtolower(trim($type));

		if($type === 'keyword') return 'keyword';
		if($type === 'integer') return 'integer';
		if($type === 'float') return 'float';
		if($type === 'bool') return 'bool';
		if($type === 'text') return 'text';
		if($type === 'uuid') return 'uuid';

		return null;
	}

	private function buildPointId(AgentEmbeddingChunk $chunk): string {
		$hash = trim((string)$chunk->hash);
		$idx = (int)$chunk->chunkIndex;

		if($hash !== '') {
			$base = $hash . ':' . $idx;
			return $this->uuidV5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', $base);
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
		$timeLow = substr($hash, 0, 8);
		$timeMid = substr($hash, 8, 4);
		$timeHi = substr($hash, 12, 4);
		$clkSeq = substr($hash, 16, 4);
		$node = substr($hash, 20, 12);
		$timeHiVal = (hexdec($timeHi) & 0x0fff) | 0x5000;
		$clkSeqVal = (hexdec($clkSeq) & 0x3fff) | 0x8000;

		return sprintf('%s-%s-%04x-%04x-%s', $timeLow, $timeMid, $timeHiVal, $clkSeqVal, $node);
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

		if($timeout > 0) {
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		}

		if($connectTimeout > 0) {
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
		}

		if($method === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
		}
		elseif($method !== 'GET') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}

		if($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}

		$raw = curl_exec($ch);
		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		return [
			'raw' => $raw,
			'http' => $http,
			'error' => $error
		];
	}

	private function assertReady(): void {
		if(!$this->baseUrl || trim($this->baseUrl) === '') {
			throw new \RuntimeException(static::getName() . ' not configured: base URL missing.');
		}

		if(!$this->authSecret || trim($this->authSecret) === '') {
			throw new \RuntimeException(static::getName() . ' not configured: connection auth secret missing.');
		}
	}
}
