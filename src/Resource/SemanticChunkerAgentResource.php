<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentChunker;
use MissionBay\Dto\AgentParsedContent;

class SemanticChunkerAgentResource extends AbstractAgentResource implements IAgentChunker {

	protected int $maxLength = 800;
	protected int $minLength = 200;
	protected int $overlap = 50;

	public static function getName(): string {
		return 'semanticchunkeragentresource';
	}

	public function getDescription(): string {
		return 'Semantic chunker splitting text by paragraphs and sentences.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);
		$this->maxLength = $config['max_length'] ?? 800;
		$this->minLength = $config['min_length'] ?? 200;
		$this->overlap = $config['overlap'] ?? 50;
	}

	public function getPriority(): int {
		return 100;
	}

	public function supports(AgentParsedContent $parsed): bool {
		return is_string($parsed->text) && strlen($parsed->text) > 0;
	}

	public function chunk(AgentParsedContent $parsed): array {
		$text = $parsed->text;
		$meta = $parsed->metadata;

		$paragraphs = $this->splitParagraphs($text);
		$chunks = [];

		foreach ($paragraphs as $p) {
			$chunks = array_merge(
				$chunks,
				$this->splitParagraphIntoChunks($p, $meta)
			);
		}

		return $chunks;
	}

	// ---------------------------------------------------------
	// Text splitting
	// ---------------------------------------------------------

	private function splitParagraphs(string $text): array {
		$parts = preg_split('/\n{2,}/u', trim($text));
		return array_filter(array_map('trim', $parts));
	}

	private function splitSentences(string $text): array {
		$parts = preg_split(
			'/(?<=[.!?])\s+(?=[A-ZÄÖÜ])/u',
			$text
		);
		return array_filter(array_map('trim', $parts));
	}

	// ---------------------------------------------------------
	// Chunk logic
	// ---------------------------------------------------------

	private function splitParagraphIntoChunks(string $paragraph, array $meta): array {
		$sentences = $this->splitSentences($paragraph);
		$chunks = [];

		$current = '';

		foreach ($sentences as $sentence) {
			if (strlen($current) + strlen($sentence) > $this->maxLength) {
				if ($current !== '') {
					$chunks[] = $this->makeChunk($current, $meta);
				}
				$current = $sentence;
				continue;
			}

			$current .= ($current === '' ? '' : ' ') . $sentence;
		}

		if ($current !== '') {
			$chunks[] = $this->makeChunk($current, $meta);
		}

		$chunks = $this->enforceMinSize($chunks, $meta);

		if ($this->overlap > 0) {
			$chunks = $this->applyOverlap($chunks, $meta);
		}

		return $chunks;
	}

	private function enforceMinSize(array $chunks, array $meta): array {
		if (count($chunks) < 2) {
			return $chunks;
		}

		$out = [];
		$buffer = '';

		foreach ($chunks as $c) {
			if (strlen($c['text']) < $this->minLength) {
				$buffer .= ($buffer === '' ? '' : "\n") . $c['text'];
				continue;
			}

			if ($buffer !== '') {
				$out[] = $this->makeChunk(trim($buffer), $meta);
				$buffer = '';
			}

			$out[] = $c;
		}

		if ($buffer !== '') {
			$out[] = $this->makeChunk(trim($buffer), $meta);
		}

		return $out;
	}

	private function applyOverlap(array $chunks, array $meta): array {
		$out = [];

		for ($i = 0; $i < count($chunks); $i++) {
			$current = $chunks[$i]['text'];

			if ($i > 0) {
				$prev = $chunks[$i - 1]['text'];
				$tail = mb_substr($prev, -$this->overlap);
				$current = $tail . "\n" . $current;
			}

			$out[] = $this->makeChunk($current, $meta);
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
