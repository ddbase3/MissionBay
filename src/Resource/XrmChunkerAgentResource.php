<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentChunker;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Dto\AgentParsedContent;

class XrmChunkerAgentResource extends AbstractAgentResource implements IAgentChunker {

	protected IAgentConfigValueResolver $resolver;

	protected int $maxLength = 800;
	protected int $minLength = 200;
	protected int $overlap = 50;

	protected array $inlineMetaFields = ['name', 'tags', 'type'];

	protected array $resolvedOptions = [];

	public static function getName(): string {
		return 'xrmchunkeragentresource';
	}

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public function getDescription(): string {
		return 'RAG chunker for XRM entities: merge all fields, sticky headings, meta in every chunk.';
	}

	public function getPriority(): int {
		return 40;
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->maxLength = $this->resolveInt($config, 'max_length', 800);
		$this->minLength = $this->resolveInt($config, 'min_length', 200);
		$this->overlap = $this->resolveInt($config, 'overlap', 50);

		$this->inlineMetaFields = $this->resolveInlineMetaFields($config);

		$this->resolvedOptions = [
			'max_length' => $this->maxLength,
			'min_length' => $this->minLength,
			'overlap' => $this->overlap,
			'inline_meta_fields' => $this->inlineMetaFields
		];
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function supports(AgentParsedContent $parsed): bool {
		if (!is_array($parsed->structured) && !is_object($parsed->structured)) {
			return false;
		}

		$root = (array)$parsed->structured;

		if (isset($root['payload']) && (is_array($root['payload']) || is_object($root['payload']))) {
			return true;
		}

		return isset($root['id'], $root['data']);
	}

	public function chunk(AgentParsedContent $parsed): array {
		$root = (array)$parsed->structured;
		$meta = $parsed->metadata;

		if (isset($root['payload'])) {
			$data = (array)$root['payload'];
			return $this->chunkStructured($root, $data, $meta, true);
		}

		$data = (array)($root['data'] ?? []);
		return $this->chunkStructured($root, $data, $meta, false);
	}

	protected function chunkStructured(array $root, array $data, array $meta, bool $isNewShape): array {
		$inlineMeta = $this->buildInlineMetadata($root, $data);

		$metaLines = [];
		$textSections = [];

		/*
		 * Key fix:
		 * Treat short strings as metadata lines (not as their own "## <Key>" text sections).
		 * This prevents tiny "## Name" chunks and keeps the first chunk useful.
		 */
		$shortTextThreshold = max(100, (int)floor($this->minLength / 2)); // e.g. minLength=500 => 250

		foreach ($data as $key => $value) {
			if ($value === null || $value === '' || $value === '0' || $value === 0) {
				continue;
			}

			if (is_numeric($value) || is_bool($value)) {
				$metaLines[] = ucfirst((string)$key) . ": " . json_encode($value);
				continue;
			}

			if (is_string($value)) {
				$val = trim($value);

				if ($val === '') {
					continue;
				}

				$len = mb_strlen($val);

				if ($len <= $shortTextThreshold) {
					$metaLines[] = ucfirst((string)$key) . ": " . $val;
					continue;
				}

				$textSections[(string)$key] = $this->normalizeNewlines($val);
				continue;
			}

			if (is_array($value) || is_object($value)) {
				$json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
				$json = $this->normalizeNewlines((string)$json);

				if (mb_strlen($json) <= 100) {
					$metaLines[] = ucfirst((string)$key) . ": " . trim($json);
				} else {
					$textSections[(string)$key] = $json;
				}
				continue;
			}
		}

		$name = $this->pickName($root, $data, $isNewShape);
		$fullText = $this->buildFullText($name, $metaLines, $textSections);

		$rawChunks = $this->chunkTextMaxFit($fullText);

		if ($this->overlap > 0 && count($rawChunks) > 1) {
			$rawChunks = $this->applyOverlapRaw($rawChunks);
		}

		$out = [];
		foreach ($rawChunks as $raw) {
			$out[] = $this->makeChunk($this->prefixMeta($inlineMeta, $raw), $meta);
		}

		return $out;
	}

	protected function buildFullText(string $name, array $metaLines, array $textSections): string {
		$parts = [];

		$parts[] = "# " . $name;
		$parts[] = "";
		$parts[] = "## Metadata";
		if ($metaLines) {
			$parts[] = implode("\n", $metaLines);
		}

		foreach ($textSections as $key => $content) {
			$parts[] = "";
			$parts[] = "## " . ucfirst((string)$key);
			$parts[] = "";
			$parts[] = trim((string)$content);
		}

		return trim(implode("\n", $parts));
	}

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

	protected function buildInlineMetadata(array $root, array $data): string {
		$pairs = [];

		foreach ($this->inlineMetaFields as $field) {
			$val = null;

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

		if (!$pairs) {
			return "<!-- meta: -->";
		}

		return "<!-- meta: " . implode("; ", $pairs) . " -->";
	}

	private function resolveInt(array $config, string $key, int $default): int {
		$value = $this->resolver->resolveValue($config[$key] ?? $default);
		return (int)$value;
	}

	private function resolveInlineMetaFields(array $config): array {
		$value = $this->resolver->resolveValue($config['inline_meta_fields'] ?? $this->inlineMetaFields);

		if (is_string($value)) {
			$items = array_map('trim', explode(',', $value));
			$items = array_values(array_filter($items, fn($v) => $v !== ''));
			return $items ?: $this->inlineMetaFields;
		}

		if (is_array($value)) {
			$out = [];
			foreach ($value as $v) {
				$s = trim((string)$v);
				if ($s !== '') {
					$out[] = $s;
				}
			}
			return $out ?: $this->inlineMetaFields;
		}

		return $this->inlineMetaFields;
	}

	private function normalizeNewlines(string $text): string {
		return preg_replace('/\R/u', "\n", $text);
	}

	private function prefixMeta(string $inlineMeta, string $text): string {
		return trim($inlineMeta . "\n" . trim($text));
	}

	private function chunkTextMaxFit(string $text): array {
		$text = $this->normalizeNewlines($text);

		if (mb_strlen($text) <= $this->maxLength) {
			return [$text];
		}

		$paras = $this->splitParagraphs($text);
		$paras = $this->mergeStickyHeadings($paras);

		$out = [];
		$current = '';

		foreach ($paras as $p) {
			$p = trim($p);
			if ($p === '') {
				continue;
			}

			$candidate = ($current === '' ? $p : $current . "\n\n" . $p);

			if (mb_strlen($candidate) <= $this->maxLength) {
				$current = $candidate;
				continue;
			}

			if ($current !== '') {
				$out[] = trim($current);
				$current = '';
			}

			if (mb_strlen($p) <= $this->maxLength) {
				$current = $p;
				continue;
			}

			foreach ($this->splitByLinesMaxFit($p) as $part) {
				$out[] = $part;
			}
		}

		if ($current !== '') {
			$out[] = trim($current);
		}

		return $this->enforceMinSizeRaw($out);
	}

	private function splitParagraphs(string $text): array {
		$parts = preg_split("/\n{2,}/u", trim($text));
		if (!$parts || count($parts) === 0) {
			return [trim($text)];
		}
		return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
	}

	private function mergeStickyHeadings(array $paras): array {
		$out = [];
		$count = count($paras);

		for ($i = 0; $i < $count; $i++) {
			$p = trim((string)$paras[$i]);
			if ($p === '') {
				continue;
			}

			if ($this->isHeadingOnly($p) && $i + 1 < $count) {
				$next = trim((string)$paras[$i + 1]);
				if ($next !== '') {
					$out[] = $p . "\n\n" . $next;
					$i++;
					continue;
				}
			}

			$out[] = $p;
		}

		return $out;
	}

	private function isHeadingOnly(string $para): bool {
		$para = trim($para);
		if ($para === '') {
			return false;
		}
		if (!preg_match('/^#{1,6}\s+/u', $para)) {
			return false;
		}
		return (substr_count($para, "\n") === 0);
	}

	private function splitByLinesMaxFit(string $text): array {
		$text = $this->normalizeNewlines($text);
		$lines = explode("\n", $text);

		$out = [];
		$current = '';

		foreach ($lines as $line) {
			$line = rtrim($line);

			$candidate = ($current === '' ? $line : $current . "\n" . $line);

			if (mb_strlen($candidate) <= $this->maxLength) {
				$current = $candidate;
				continue;
			}

			if ($current !== '') {
				$out[] = trim($current);
				$current = '';
			}

			if (mb_strlen($line) <= $this->maxLength) {
				$current = $line;
				continue;
			}

			foreach ($this->hardSplit($line, $this->maxLength) as $part) {
				$out[] = $part;
			}
		}

		if ($current !== '') {
			$out[] = trim($current);
		}

		return $out;
	}

	private function hardSplit(string $text, int $max): array {
		$text = trim($text);
		if ($max < 50) {
			$max = 50;
		}

		$out = [];
		$len = mb_strlen($text);
		$offset = 0;

		while ($offset < $len) {
			$out[] = trim(mb_substr($text, $offset, $max));
			$offset += $max;
		}

		return $out;
	}

	private function enforceMinSizeRaw(array $chunks): array {
		if (count($chunks) < 2) {
			return $chunks;
		}

		$out = [];
		$buffer = '';

		foreach ($chunks as $c) {
			if (mb_strlen($c) < $this->minLength) {
				$buffer .= ($buffer === '' ? '' : "\n\n") . $c;
				continue;
			}

			if ($buffer !== '') {
				$out[] = trim($buffer);
				$buffer = '';
			}

			$out[] = trim($c);
		}

		if ($buffer !== '') {
			$out[] = trim($buffer);
		}

		return $out;
	}

	private function applyOverlapRaw(array $chunks): array {
		$out = [];
		$count = count($chunks);

		for ($i = 0; $i < $count; $i++) {
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

	private function makeChunk(string $text, array $meta): array {
		return [
			'id' => uniqid('chunk_', true),
			'text' => trim($text),
			'meta' => $meta
		];
	}
}
