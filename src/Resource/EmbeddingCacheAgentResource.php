<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiEmbeddingModel;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContext;

final class EmbeddingCacheAgentResource extends AbstractAgentResource implements IAiEmbeddingModel {

	private IDatabase $db;
	private IAgentConfigValueResolver $resolver;

	private ?IAiEmbeddingModel $embedding = null;
	private ?ILogger $logger = null;

	private string $table = 'base3_embedding_cache';
	private bool $tableReady = false;

	private array|string|null $tableConfig = null;
	private array|string|null $saltConfig = null;
	private array|string|null $modelConfig = null;

	private ?string $cacheSalt = null;
	private ?string $cacheModel = null;

	public function __construct(IDatabase $db, IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->db = $db;
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'embeddingcacheagentresource';
	}

	public function getDescription(): string {
		return 'DB-backed embedding cache proxy. Docks a real embedding model (dock: embedding).';
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'embedding',
				description: 'The real embedding model behind the cache.',
				interface: IAiEmbeddingModel::class,
				maxConnections: 1,
				required: true
			),
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->tableConfig = $config['table'] ?? null;
		$this->saltConfig = $config['salt'] ?? null;   // cache-only hash namespace
		$this->modelConfig = $config['model'] ?? null; // optional hash override

		$table = $this->resolver->resolveValue($this->tableConfig);
		if (is_string($table) && trim($table) !== '') {
			$this->table = trim($table);
		}

		$salt = $this->resolver->resolveValue($this->saltConfig);
		if (is_string($salt) && trim($salt) !== '') {
			$this->cacheSalt = trim($salt);
		}

		$model = $this->resolver->resolveValue($this->modelConfig);
		if (is_string($model) && trim($model) !== '') {
			$this->cacheModel = trim($model);
		}
	}

	public function init(array $resources, IAgentContext $context): void {
		if (isset($resources['embedding'][0]) && $resources['embedding'][0] instanceof IAiEmbeddingModel) {
			$this->embedding = $resources['embedding'][0];
		}
		if (isset($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
			$this->logger = $resources['logger'][0];
		}

		$this->log('Initialized (table=' . $this->table . ')');
	}

	// ---------------------------------------------------------
	// IAiEmbeddingModel
	// ---------------------------------------------------------

	public function embed(array $texts): array {
		if (empty($texts)) {
			return [];
		}
		if (!$this->embedding) {
			throw new \RuntimeException('EmbeddingCacheAgentResource: Missing dock "embedding".');
		}

		$this->ensureTable();

		$model = $this->getModelScope();
		$salt = $this->getSaltScope();

		$hashes = $this->buildHashes($texts, $model, $salt);
		$cached = $this->loadCachedVectors($hashes, $model);

		$result = array_fill(0, count($texts), []);
		$missingTexts = [];
		$missingIndexMap = [];

		foreach ($hashes as $i => $hash) {
			if (isset($cached[$hash])) {
				$result[$i] = $cached[$hash];
				continue;
			}

			$missingIndexMap[] = $i;
			$missingTexts[] = (string)$texts[$i];
		}

		if (!empty($missingTexts)) {
			$this->log('Cache miss: ' . count($missingTexts) . ' / ' . count($texts));

			$embedded = $this->embedding->embed($missingTexts);

			foreach ($missingIndexMap as $k => $originalIndex) {
				$vector = $embedded[$k] ?? [];
				$result[$originalIndex] = $vector;

				if (!is_array($vector) || empty($vector)) {
					continue;
				}

				$hash = $hashes[$originalIndex];
				$this->storeVector($hash, $model, count($vector), $vector);
			}
		} else {
			$this->log('Cache hit: ' . count($texts) . ' / ' . count($texts));
		}

		$this->touchRows($hashes, $model);

		return $result;
	}

	public function setOptions(array $options): void {
		// Cache does not own embedder options. Forward directly.
		if ($this->embedding) {
			$this->embedding->setOptions($options);
		}
	}

	public function getOptions(): array {
		// Introspection: show embedder options + cache scope
		$base = $this->embedding ? $this->embedding->getOptions() : [];

		return array_merge($base, [
			'cache_table' => $this->table,
			'cache_model_scope' => $this->cacheModel,
			'cache_salt_scope' => $this->cacheSalt
		]);
	}

	// ---------------------------------------------------------
	// Internals
	// ---------------------------------------------------------

	private function getModelScope(): string {
		if (is_string($this->cacheModel) && $this->cacheModel !== '') {
			return $this->cacheModel;
		}

		$opts = $this->embedding ? $this->embedding->getOptions() : [];
		$model = $opts['model'] ?? null;

		return (is_string($model) && $model !== '') ? $model : 'unknown';
	}

	private function getSaltScope(): string {
		if (is_string($this->cacheSalt) && $this->cacheSalt !== '') {
			return $this->cacheSalt;
		}

		// Default is stable; you can set salt explicitly if you have multiple providers.
		return 'default';
	}

	private function ensureTable(): void {
		if ($this->tableReady) {
			return;
		}

		$this->db->connect();

		$table = $this->escapeIdent($this->table);

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			hash CHAR(64) NOT NULL,
			model VARCHAR(128) NOT NULL,
			dimension INT NOT NULL,
			vector_json MEDIUMTEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_accessed_at DATETIME NULL,
			hit_count INT NOT NULL DEFAULT 0,
			PRIMARY KEY (hash, model),
			KEY idx_created (created_at),
			KEY idx_model (model)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

		$this->db->nonQuery($sql);

		$this->tableReady = true;
		$this->log('Cache table ready: ' . $this->table);
	}

	private function buildHashes(array $texts, string $model, string $salt): array {
		$out = [];

		foreach ($texts as $t) {
			$norm = $this->normalizeText((string)$t);
			$out[] = hash('sha256', $model . "\n" . $salt . "\n" . $norm);
		}

		return $out;
	}

	private function normalizeText(string $text): string {
		$text = str_replace("\r\n", "\n", $text);
		$text = preg_replace("/[ \t]+/", " ", $text) ?? $text;
		return trim($text);
	}

	private function loadCachedVectors(array $hashes, string $model): array {
		$this->db->connect();

		$in = $this->buildInList($hashes);
		if ($in === '') {
			return [];
		}

		$table = $this->escapeIdent($this->table);
		$modelEsc = $this->db->escape($model);

		$sql = "SELECT hash, vector_json
			FROM {$table}
			WHERE model = '{$modelEsc}' AND hash IN ({$in})";

		$rows = $this->db->multiQuery($sql);

		$out = [];
		foreach ($rows as $r) {
			$hash = (string)($r['hash'] ?? '');
			$json = (string)($r['vector_json'] ?? '');

			$vec = json_decode($json, true);
			if ($hash !== '' && is_array($vec)) {
				$out[$hash] = $vec;
			}
		}

		return $out;
	}

	private function storeVector(string $hash, string $model, int $dimension, array $vector): void {
		$this->db->connect();

		$json = $this->safeJson($vector);
		if ($json === '') {
			return;
		}

		$table = $this->escapeIdent($this->table);

		$hashEsc = $this->db->escape($hash);
		$modelEsc = $this->db->escape($model);
		$dim = (int)$dimension;
		$jsonEsc = $this->db->escape($json);

		$sql = "INSERT INTO {$table} (hash, model, dimension, vector_json, created_at)
			VALUES ('{$hashEsc}', '{$modelEsc}', {$dim}, '{$jsonEsc}', NOW())
			ON DUPLICATE KEY UPDATE
				dimension = VALUES(dimension),
				vector_json = VALUES(vector_json)";

		$this->db->nonQuery($sql);
	}

	private function touchRows(array $hashes, string $model): void {
		$this->db->connect();

		$in = $this->buildInList($hashes);
		if ($in === '') {
			return;
		}

		$table = $this->escapeIdent($this->table);
		$modelEsc = $this->db->escape($model);

		$sql = "UPDATE {$table}
			SET last_accessed_at = NOW(), hit_count = hit_count + 1
			WHERE model = '{$modelEsc}' AND hash IN ({$in})";

		$this->db->nonQuery($sql);
	}

	private function buildInList(array $hashes): string {
		$parts = [];

		foreach ($hashes as $h) {
			$h = (string)$h;
			if ($h === '') {
				continue;
			}
			$parts[] = "'" . $this->db->escape($h) . "'";
		}

		return implode(',', $parts);
	}

	private function escapeIdent(string $name): string {
		$clean = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? '';
		if ($clean === '') {
			$clean = 'mb_embedding_cache';
		}
		return '`' . $clean . '`';
	}

	private function safeJson(array $vector): string {
		try {
			$json = json_encode($vector, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
			return is_string($json) ? $json : '';
		} catch (\Throwable) {
			return '';
		}
	}

	private function log(string $msg): void {
		if (!$this->logger) {
			return;
		}
		$this->logger->log('EmbeddingCacheAgentResource', '[' . $this->getName() . '|' . $this->getId() . '] ' . $msg);
	}
}
