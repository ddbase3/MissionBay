<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentChunker;
use MissionBay\Dto\AgentParsedContent;

/**
 * XrmChunkerAgentResource
 *
 * Hybrid chunker for structured XRM entities.
 *
 * Supported input shapes:
 * A) New queue extractor shape:
 *    {
 *      "sysentry": {...},
 *      "type": {...},
 *      "payload": {...}
 *    }
 *
 * B) Legacy shape:
 *    {
 *      "id": ...,
 *      "data": {...}
 *    }
 *
 * Notes:
 * - chunk_index is NOT set here to avoid redundancy; AiEmbeddingNode enforces it.
 * - Inline meta "type" resolves to type.alias if available.
 */
class XrmChunkerAgentResource extends AbstractAgentResource implements IAgentChunker {

	protected int $maxLength = 800;
	protected int $minLength = 200;
	protected int $overlap   = 50;

	/** Inline meta fields to inject into every chunk */
	protected array $inlineMetaFields = ['name', 'tags', 'type'];

	public static function getName(): string {
		return 'xrmchunkeragentresource';
	}

	public function getDescription(): string {
		return 'Hybrid RAG chunker for XRM entities with sentence-based max-length chunking.';
	}

	public function getPriority(): int {
		return 40;
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->maxLength = $config['max_length'] ?? 800;
		$this->minLength = $config['min_length'] ?? 200;
		$this->overlap   = $config['overlap'] ?? 50;

		if (isset($config['inline_meta_fields']) && is_array($config['inline_meta_fields'])) {
			$this->inlineMetaFields = $config['inline_meta_fields'];
		}
	}

	public function supports(AgentParsedContent $parsed): bool {
		if (!is_array($parsed->structured) && !is_object($parsed->structured)) {
			return false;
		}

		$root = (array)$parsed->structured;

		// New shape (queue extractor)
		if (isset($root['payload']) && (is_array($root['payload']) || is_object($root['payload']))) {
			return true;
		}

		// Legacy shape
		return isset($root['id'], $root['data']);
	}

	public function chunk(AgentParsedContent $parsed): array {
		$root = (array)$parsed->structured;
		$meta = $parsed->metadata;

		// New shape
		if (isset($root['payload'])) {
			$data = (array)$root['payload'];
			return $this->chunkStructured($root, $data, $meta, true);
		}

		// Legacy shape fallback
		$data = (array)($root['data'] ?? []);
		return $this->chunkStructured($root, $data, $meta, false);
	}

	// ---------------------------------------------------------
	// Core chunking for both shapes
	// ---------------------------------------------------------

	protected function chunkStructured(array $root, array $data, array $meta, bool $isNewShape): array {
		$chunks = [];
		$index = 0;

		$inlineMeta = $this->buildInlineMetadata($root, $data);

		// -------------------------------------------------------
		// Classify fields
		// -------------------------------------------------------

		$metaLines = [];
		$textSections = [];

		foreach ($data as $key => $value) {

			if ($value === null || $value === '' || $value === '0' || $value === 0) {
				continue;
			}

			if (is_numeric($value) || is_bool($value)) {
				$metaLines[] = ucfirst((string)$key) . ": " . json_encode($value);
				continue;
			}

			if (is_string($value) && mb_strlen($value) <= 50) {
				$metaLines[] = ucfirst((string)$key) . ": " . trim($value);
				continue;
			}

			if (is_string($value)) {
				$textSections[(string)$key] = $this->normalizeNewlines($value);
				continue;
			}

			if (is_array($value) || is_object($value)) {
				$json = json_encode(
					$value,
					JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
				);
				$json = $this->normalizeNewlines((string)$json);

				if (mb_strlen($json) <= 100) {
					$metaLines[] = ucfirst((string)$key) . ": " . trim($json);
				} else {
					$textSections[(string)$key] = $json;
				}

				continue;
			}
		}

		// -------------------------------------------------------
		// CHUNK 0: Header + Metadata
		// -------------------------------------------------------

		$name = $this->pickName($root, $data, $isNewShape);

		$text =
			$inlineMeta . "\n" .
			"# " . $name . "\n\n" .
			"## Metadata\n" .
			implode("\n", $metaLines);

		$chunks[] = $this->makeChunk($text, $meta, $index++);

		// -------------------------------------------------------
		// CHUNK long text fields
		// -------------------------------------------------------

		foreach ($textSections as $key => $content) {

			$sectionTitle = "## " . ucfirst((string)$key) . "\n\n" . trim((string)$content);

			foreach ($this->chunkSentencesMaxFit($sectionTitle) as $body) {
				$finalText = $inlineMeta . "\n" . $body;
				$chunks[] = $this->makeChunk($finalText, $meta, $index++);
			}
		}

		return $chunks;
	}

	// ======================================================================
	// NAME PICKER
	// ======================================================================

	protected function pickName(array $root, array $data, bool $isNewShape): string {
		if (isset($data['name']) && is_string($data['name']) && trim($data['name']) !== '') {
			return trim($data['name']);
		}

		if (isset($root['name']) && is_string($root['name']) && trim($root['name']) !== '') {
			return trim($root['name']);
		}

		if ($isNewShape) {
			$alias = $root['type']['alias'] ?? null;
			if (is_string($alias) && $alias !== '') {
				return 'Entity (' . $alias . ')';
			}

			$id = $root['sysentry']['id'] ?? null;
			if (is_numeric($id)) {
				return 'Entity #' . (int)$id;
			}
		}

		if (isset($root['id']) && is_numeric($root['id'])) {
			return 'Entity #' . (int)$root['id'];
		}

		return 'Entity';
	}

	// ======================================================================
	// INLINE META
	// ======================================================================

	protected function buildInlineMetadata(array $root, array $data): string {
		$pairs = [];

		foreach ($this->inlineMetaFields as $field) {

			$val = null;

			// Special-case: "type" should resolve to type alias if present
			if ($field === 'type') {
				$val = $root['type']['alias'] ?? $data['type'] ?? null;
			} else {
				$val = $root[$field] ?? $data[$field] ?? null;
			}

			if ($val === null) {
				continue;
			}

			if (is_array($val)) {
				$val = implode(',', array_map('trim', array_map('strval', $val)));
			}

			if (is_object($val)) {
				$val = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			}

			$v = str_replace('"', "'", (string)$val);
			$pairs[] = "{$field}=\"{$v}\"";
		}

		if (empty($pairs)) {
			return "<!-- meta: -->";
		}

		return "<!-- meta: " . implode("; ", $pairs) . " -->";
	}

	// ======================================================================
	// SENTENCE-BASED MAX-FIT CHUNKING (NO PARAGRAPHS)
	// ======================================================================

	private function normalizeNewlines(string $text): string {
		return preg_replace('/\R/u', "\n", $text);
	}

	/**
	 * Robust max-length chunking:
	 * - Split into sentences
	 * - Pack sentences until maxLength is reached
	 * - If a sentence is too long, split it hard
	 */
	private function chunkSentencesMaxFit(string $text): array {
		$text = $this->normalizeNewlines($text);

		$sentences = $this->splitIntoSentencesFallback($text);

		$out = [];
		$current = '';

		foreach ($sentences as $s) {

			$s = trim($s);
			$lenS = mb_strlen($s);

			// If sentence is longer than allowed: hard split
			if ($lenS > $this->maxLength) {
				if ($current !== '') {
					$out[] = trim($current);
					$current = '';
				}

				$offset = 0;
				while ($offset < $lenS) {
					$chunkPart = mb_substr($s, $offset, $this->maxLength);
					$out[] = trim($chunkPart);
					$offset += $this->maxLength;
				}
				continue;
			}

			// Normal case: fit into current chunk?
			$currentLen = mb_strlen($current);

			if ($currentLen + $lenS + 1 <= $this->maxLength) {
				$current .= ($current === '' ? '' : ' ') . $s;
				continue;
			}

			// chunk full → commit
			if ($current !== '') {
				$out[] = trim($current);
			}
			$current = $s;
		}

		if ($current !== '') {
			$out[] = trim($current);
		}

		// minLength enforcement
		$out = $this->enforceMinSize($out);

		// overlap logic
		if ($this->overlap > 0) {
			$out = $this->applyOverlap($out);
		}

		return $out;
	}

	/**
	 * More tolerant sentence splitting. If regex fails, fallback is whole text.
	 */
	private function splitIntoSentencesFallback(string $text): array {
		$parts = preg_split('/(?<=[.!?])\s+(?=[A-ZÄÖÜ])/u', $text);
		if (!$parts || count($parts) === 0) {
			return [$text];
		}
		return array_filter(array_map('trim', $parts));
	}

	private function enforceMinSize(array $chunks): array {
		if (count($chunks) < 2) {
			return $chunks;
		}

		$out = [];
		$buffer = '';

		foreach ($chunks as $c) {
			if (mb_strlen($c) < $this->minLength) {
				$buffer .= ($buffer === '' ? '' : "\n") . $c;
				continue;
			}

			if ($buffer !== '') {
				$out[] = trim($buffer);
				$buffer = '';
			}

			$out[] = $c;
		}

		if ($buffer !== '') {
			$out[] = trim($buffer);
		}

		return $out;
	}

	private function applyOverlap(array $chunks): array {
		$out = [];

		for ($i = 0; $i < count($chunks); $i++) {

			$current = $chunks[$i];

			if ($i > 0) {
				$prev = $chunks[$i - 1];
				$tail = mb_substr($prev, -$this->overlap);
				$current = trim($tail . "\n" . $current);
			}

			$out[] = $current;
		}

		return $out;
	}

	private function makeChunk(string $text, array $meta, int $index): array {
		// Do NOT set chunk_index here (avoid redundancy).
		// AiEmbeddingNode enforces chunk_index at store time.
		return [
			'id'   => uniqid('chunk_', true),
			'text' => trim($text),
			'meta' => $meta
		];
	}
}
