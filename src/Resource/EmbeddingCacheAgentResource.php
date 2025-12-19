<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiEmbeddingModel;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContext;

class EmbeddingCacheAgentResource extends AbstractAgentResource implements IAiEmbeddingModel {

	private IDatabase $db;
	private IAgentConfigValueResolver $resolver;

	private ?IAiEmbeddingModel $embedding = null;
	private ?ILogger $logger = null;

	private array|string|null $tableConfig = null;

	private string $table = 'mb_embedding_cache';
	private bool $tableReady = false;

	private array $resolvedOptions = [];

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

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->tableConfig = $config['table'] ?? null;

		$table = $this->resolver->resolveValue($this->tableConfig);
		if (is_string($table) && $table !== '') {
			$this->table = $table;
		}

		// Optional: allow passing IAiEmbeddingModel options via config
		if (isset($config['options']) && is_array($config['options'])) {
			$this->setOptions($config['options']);
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
		if (empty($texts)) return [];
		if (!$this->embedding) {
			throw new \RuntimeException('EmbeddingCacheAgentResource: Missing dock "embedding".');
		}

		// Forward cache-level options to the real embedder
		if (!empty($this->resolvedOptions)) {
			$this->embedding->setOptions($this->resolvedOptions);
		}

		$this->ensureTable();

		$model = $this->getEffectiveModelName();
		$salt = $this->getEffectiveSalt();

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

				if (empty($vector)) continue;

				$hash = $hashes[$originalIndex];
				$dim = count($vector);

				$this->storeVector($hash, $model, $dim, $vector);
			}
		} else {
			$this->log('Cache hit: ' . count($texts) . ' / ' . count($texts));
		}

		$this->touchRows($hashes, $model);

		return $result;
	}

	public function setOptions(array $options): void {
		// Merge and keep options at cache-level
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);

		// Forward immediately if embedder is already available
		if ($this->embedding) {
			$this->embedding->setOptions($options);
		}
	}

	public function getOptions(): array {
		// Expose combined options for introspection/debugging
		$base = [];

		if ($this->embedding) {
			$base = $this->embedding->getOptions();
		}

		return array_merge($base, $this->resolvedOptions, [
			'table' => $this->table
		]);
	}

	// ---------------------------------------------------------
	// Internals
	// ---------------------------------------------------------

	private function ensureTable(): void {
		if ($this->tableReady) return;

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

		$table = $this->escapeIdent($this->table);
		$modelEsc = $this->db->escape($model);

		$in = $this->buildInList($hashes);
		if ($in === '') return [];

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

		$table = $this->escapeIdent($this->table);

		$hashEsc = $this->db->escape($hash);
		$modelEsc = $this->db->escape($model);
		$dim = (int)$dimension;

		$json = json_encode($vector);
		if (!is_string($json) || $json === '') return;
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

		$table = $this->escapeIdent($this->table);
		$modelEsc = $this->db->escape($model);

		$in = $this->buildInList($hashes);
		if ($in === '') return;

		$sql = "UPDATE {$table}
			SET last_accessed_at = NOW(), hit_count = hit_count + 1
			WHERE model = '{$modelEsc}' AND hash IN ({$in})";

		$this->db->nonQuery($sql);
	}

	private function buildInList(array $hashes): string {
		$parts = [];

		foreach ($hashes as $h) {
			$h = (string)$h;
			if ($h === '') continue;
			$parts[] = "'" . $this->db->escape($h) . "'";
		}

		return implode(',', $parts);
	}

	private function escapeIdent(string $name): string {
		// Basic identifier escaping for MySQL: allow only [a-zA-Z0-9_]
		$clean = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? '';
		if ($clean === '') $clean = 'mb_embedding_cache';
		return '`' . $clean . '`';
	}

	private function getEffectiveModelName(): string {
		// Prefer explicit options on cache
		if (isset($this->resolvedOptions['model']) && is_string($this->resolvedOptions['model']) && $this->resolvedOptions['model'] !== '') {
			return $this->resolvedOptions['model'];
		}

		// Otherwise use embedder options
		$opts = $this->embedding ? $this->embedding->getOptions() : [];
		if (isset($opts['model']) && is_string($opts['model']) && $opts['model'] !== '') {
			return $opts['model'];
		}

		return 'unknown';
	}

	private function getEffectiveSalt(): string {
		// Optional: add endpoint/provider to avoid collisions across vendors
		$endpoint = null;

		if (isset($this->resolvedOptions['endpoint']) && is_string($this->resolvedOptions['endpoint']) && $this->resolvedOptions['endpoint'] !== '') {
			$endpoint = $this->resolvedOptions['endpoint'];
		} else {
			$opts = $this->embedding ? $this->embedding->getOptions() : [];
			if (isset($opts['endpoint']) && is_string($opts['endpoint']) && $opts['endpoint'] !== '') {
				$endpoint = $opts['endpoint'];
			}
		}

		return $endpoint ? $endpoint : 'default';
	}

	private function log(string $msg): void {
		if (!$this->logger) return;
		$this->logger->log('EmbeddingCacheAgentResource', '[' . $this->getName() . '|' . $this->getId() . '] ' . $msg);
	}
}
