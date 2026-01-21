<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * ConsoleVectorStoreAgentResource
 *
 * Debug vector store that prints all upsert/search/delete operations to STDOUT
 * instead of persisting anything.
 *
 * - Uses the normalizer to validate and build payloads (same as real store).
 * - Tries to be deterministic and readable on CLI.
 * - Keeps minimal in-memory counters only (no storage).
 */
final class ConsoleVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

	protected IAgentConfigValueResolver $resolver;
	protected IAgentRagPayloadNormalizer $normalizer;

	protected mixed $vectorPreviewDimsConfig = null;
	protected mixed $vectorPreviewDecimalsConfig = null;
	protected mixed $payloadPreviewBytesConfig = null;
	protected mixed $printTextPreviewConfig = null;

	protected int $vectorPreviewDims = 16;
	protected int $vectorPreviewDecimals = 4;
	protected int $payloadPreviewBytes = 1200;
	protected int $textPreviewChars = 240;

	private int $upsertCount = 0;
	private int $deleteCount = 0;
	private int $searchCount = 0;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		IAgentRagPayloadNormalizer $normalizer,
		?string $id = null
	) {
		parent::__construct($id);
		$this->resolver = $resolver;
		$this->normalizer = $normalizer;
	}

	public static function getName(): string {
		return 'consolevectorstoreagentresource';
	}

	public function getDescription(): string {
		return 'Debug VectorStore: prints normalized payload + vector preview to console (no persistence).';
	}

	public function getPriority(): int {
		return 1;
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->vectorPreviewDimsConfig = $config['vector_preview_dims'] ?? 16;
		$this->vectorPreviewDecimalsConfig = $config['vector_preview_decimals'] ?? 4;
		$this->payloadPreviewBytesConfig = $config['payload_preview_bytes'] ?? 1200;
		$this->printTextPreviewConfig = $config['text_preview_chars'] ?? 240;

		$this->vectorPreviewDims = $this->asInt($this->resolver->resolveValue($this->vectorPreviewDimsConfig), 16);
		$this->vectorPreviewDecimals = $this->asInt($this->resolver->resolveValue($this->vectorPreviewDecimalsConfig), 4);
		$this->payloadPreviewBytes = $this->asInt($this->resolver->resolveValue($this->payloadPreviewBytesConfig), 1200);
		$this->textPreviewChars = $this->asInt($this->resolver->resolveValue($this->printTextPreviewConfig), 240);

		if ($this->vectorPreviewDims < 1) $this->vectorPreviewDims = 16;
		if ($this->vectorPreviewDecimals < 0) $this->vectorPreviewDecimals = 4;
		if ($this->payloadPreviewBytes < 200) $this->payloadPreviewBytes = 1200;
		if ($this->textPreviewChars < 0) $this->textPreviewChars = 240;
	}

	// ---------------------------------------------------------
	// UPSERT
	// ---------------------------------------------------------

	public function upsert(AgentEmbeddingChunk $chunk): void {
		$this->normalizer->validate($chunk);

		$collectionKey = trim((string)$chunk->collectionKey);
		$collection = $this->normalizer->getBackendCollectionName($collectionKey);

		$payload = $this->normalizer->buildPayload($chunk);
		$pointId = $this->buildPointId($chunk);

		$this->upsertCount++;

		$this->println('');
		$this->println($this->line('UPSERT', $this->upsertCount));
		$this->println("collectionKey: {$collectionKey}");
		$this->println("collection: {$collection}");
		$this->println("pointId: {$pointId}");
		$this->println("hash: " . (string)$chunk->hash);
		$this->println("chunkIndex: " . (string)$chunk->chunkIndex);

		$text = (string)$chunk->text;
		if ($this->textPreviewChars > 0) {
			$this->println("text(" . strlen($text) . "): " . $this->previewText($text, $this->textPreviewChars));
		} else {
			$this->println("text(" . strlen($text) . "): [hidden]");
		}

		$vector = is_array($chunk->vector) ? $chunk->vector : [];
		$this->println("vectorDims: " . count($vector));
		$this->println("vectorPreview: " . $this->previewVector($vector));

		$this->println("payloadPreview: " . $this->previewJson($payload, $this->payloadPreviewBytes));
	}

	// ---------------------------------------------------------
	// EXISTS
	// ---------------------------------------------------------

	public function existsByHash(string $collectionKey, string $hash): bool {
		$this->println('');
		$this->println($this->line('EXISTS_BY_HASH', null));
		$this->println("collectionKey: " . trim($collectionKey));
		$this->println("hash: " . trim($hash));
		$this->println("result: false (console store)");
		return false;
	}

	public function existsByFilter(string $collectionKey, array $filter): bool {
		$this->println('');
		$this->println($this->line('EXISTS_BY_FILTER', null));
		$this->println("collectionKey: " . trim($collectionKey));
		$this->println("filter: " . $this->previewJson($filter, $this->payloadPreviewBytes));
		$this->println("result: false (console store)");
		return false;
	}

	// ---------------------------------------------------------
	// DELETE
	// ---------------------------------------------------------

	public function deleteByFilter(string $collectionKey, array $filter): int {
		$this->deleteCount++;

		$this->println('');
		$this->println($this->line('DELETE_BY_FILTER', $this->deleteCount));
		$this->println("collectionKey: " . trim($collectionKey));
		$this->println("filter: " . $this->previewJson($filter, $this->payloadPreviewBytes));
		$this->println("deleted: 0 (console store)");
		return 0;
	}

	// ---------------------------------------------------------
	// SEARCH
	// ---------------------------------------------------------

	public function search(string $collectionKey, array $vector, int $limit = 3, ?float $minScore = null, ?array $filterSpec = null): array {
		$this->searchCount++;

		$collectionKey = trim($collectionKey);
		$collection = $this->normalizer->getBackendCollectionName($collectionKey);

		$this->println('');
		$this->println($this->line('SEARCH', $this->searchCount));
		$this->println("collectionKey: {$collectionKey}");
		$this->println("collection: {$collection}");
		$this->println("limit: {$limit}");
		$this->println("minScore: " . ($minScore === null ? 'null' : (string)$minScore));
		$this->println("vectorDims: " . count($vector));
		$this->println("vectorPreview: " . $this->previewVector($vector));

		if ($filterSpec !== null) {
			$this->println("filterSpec: " . $this->previewJson($filterSpec, $this->payloadPreviewBytes));
		} else {
			$this->println("filterSpec: null");
		}

		$this->println("result: [] (console store)");
		return [];
	}

	// ---------------------------------------------------------
	// COLLECTION HELPERS
	// ---------------------------------------------------------

	public function createCollection(string $collectionKey): void {
		$collectionKey = trim($collectionKey);
		$collection = $this->normalizer->getBackendCollectionName($collectionKey);

		$this->println('');
		$this->println($this->line('CREATE_COLLECTION', null));
		$this->println("collectionKey: {$collectionKey}");
		$this->println("collection: {$collection}");

		$vectorSize = $this->normalizer->getVectorSize($collectionKey);
		$distance = $this->normalizer->getDistance($collectionKey);

		$this->println("vector_size: " . (string)$vectorSize);
		$this->println("distance: " . (string)$distance);

		$schema = $this->normalizer->getSchema($collectionKey);
		$this->println("payload_schema_preview: " . $this->previewJson($schema, $this->payloadPreviewBytes));
	}

	public function deleteCollection(string $collectionKey): void {
		$collectionKey = trim($collectionKey);
		$collection = $this->normalizer->getBackendCollectionName($collectionKey);

		$this->println('');
		$this->println($this->line('DELETE_COLLECTION', null));
		$this->println("collectionKey: {$collectionKey}");
		$this->println("collection: {$collection}");
	}

	public function getInfo(string $collectionKey): array {
		$collectionKey = trim($collectionKey);
		$collection = $this->normalizer->getBackendCollectionName($collectionKey);

		$info = [
			'collection_key' => $collectionKey,
			'collection' => $collection,
			'vector_size' => $this->normalizer->getVectorSize($collectionKey),
			'distance' => $this->normalizer->getDistance($collectionKey),
			'payload_schema' => $this->normalizer->getSchema($collectionKey),
			'backend' => 'console'
		];

		$this->println('');
		$this->println($this->line('GET_INFO', null));
		$this->println($this->previewJson($info, $this->payloadPreviewBytes));

		return $info;
	}

	// ---------------------------------------------------------
	// INTERNALS
	// ---------------------------------------------------------

	private function buildPointId(AgentEmbeddingChunk $chunk): string {
		$hash = trim((string)$chunk->hash);
		$idx = (int)$chunk->chunkIndex;

		if ($hash !== '') {
			return $this->uuidV5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', $hash . ':' . $idx);
		}

		return $this->generateUuid();
	}

	private function uuidV5(string $namespaceUuid, string $name): string {
		$nsHex = str_replace('-', '', strtolower(trim($namespaceUuid)));
		if (strlen($nsHex) !== 32 || !ctype_xdigit($nsHex)) {
			throw new \InvalidArgumentException('uuidV5: invalid namespace UUID.');
		}

		$nsBin = hex2bin($nsHex);
		if ($nsBin === false) {
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

		return sprintf(
			'%s-%s-%04x-%04x-%s',
			$timeLow,
			$timeMid,
			$timeHiVal,
			$clkSeqVal,
			$node
		);
	}

	protected function generateUuid(): string {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

	private function previewVector(array $vector): string {
		$max = $this->vectorPreviewDims;

		$out = [];
		$n = count($vector);
		$take = min($max, $n);

		for ($i = 0; $i < $take; $i++) {
			$v = $vector[$i] ?? null;
			if (!is_numeric($v)) {
				$out[] = 'NaN';
				continue;
			}
			$out[] = number_format((float)$v, $this->vectorPreviewDecimals, '.', '');
		}

		$tail = ($n > $take) ? " … +" . ($n - $take) : '';
		return '[' . implode(', ', $out) . ']' . $tail;
	}

	private function previewText(string $text, int $maxChars): string {
		$text = str_replace(["\r\n", "\r"], "\n", $text);
		$text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
		$text = trim($text);

		if ($maxChars <= 0) {
			return '';
		}

		if (mb_strlen($text) <= $maxChars) {
			return $text;
		}

		return mb_substr($text, 0, $maxChars) . ' …';
	}

	private function previewJson(mixed $value, int $maxBytes): string {
		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($json)) {
			return '[unserializable]';
		}

		if ($maxBytes <= 0) {
			return $json;
		}

		if (strlen($json) <= $maxBytes) {
			return $json;
		}

		return substr($json, 0, $maxBytes) . '…';
	}

	private function println(string $line): void {
		echo $line . PHP_EOL;
	}

	private function line(string $op, ?int $n): string {
		if ($n === null) {
			return "=== {$op} ===";
		}
		return "=== {$op} #{$n} ===";
	}

	private function asInt(mixed $value, int $fallback): int {
		if (is_int($value)) {
			return $value;
		}
		if (is_bool($value)) {
			return $value ? 1 : 0;
		}
		if (is_numeric($value)) {
			return (int)$value;
		}
		return $fallback;
	}
}
