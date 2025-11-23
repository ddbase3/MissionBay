<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentChunker;
use MissionBay\Dto\AgentParsedContent;

class SemanticChunkerAgentResource extends AbstractAgentResource implements IAgentChunker {

	protected int $maxLength = 800;
	protected int $minLength = 200;
	protected int $overlap   = 50;

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
		$this->overlap   = $config['overlap'] ?? 50;
	}

	public function getPriority(): int {
		return 100;
	}

	public function supports(AgentParsedContent $parsed): bool {
		if (!is_string($parsed->text)) {
			return false;
		}
		return trim($parsed->text) !== '';
	}

	public function chunk(AgentParsedContent $parsed): array {
		$text = trim($parsed->text ?? '');
		$meta = $parsed->metadata;

		$paragraphs = $this->splitParagraphs($text);
		$out = [];
		$index = 0;

		foreach ($paragraphs as $p) {
			$chunks = $this->splitParagraphIntoChunks($p, $meta);
			foreach ($chunks as $c) {
				$out[] = $this->makeChunk($c['text'], $meta, $index++);
			}
		}

		return $out;
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
		$out = [];
		$current = '';

		foreach ($sentences as $sentence) {
			if (strlen($current) + strlen($sentence) > $this->maxLength) {
				if ($current !== '') {
					$out[] = ['text' => trim($current), 'meta' => $meta];
				}
				$current = $sentence;
				continue;
			}

			$current .= ($current === '' ? '' : ' ') . $sentence;
		}

		if ($current !== '') {
			$out[] = ['text' => trim($current), 'meta' => $meta];
		}

		$out = $this->enforceMinSize($out, $meta);

		if ($this->overlap > 0) {
			$out = $this->applyOverlap($out, $meta);
		}

		return $out;
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
				$out[] = ['text' => trim($buffer), 'meta' => $meta];
				$buffer = '';
			}

			$out[] = $c;
		}

		if ($buffer !== '') {
			$out[] = ['text' => trim($buffer), 'meta' => $meta];
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
				$current = trim($tail . "\n" . $current);
			}

			$out[] = ['text' => $current, 'meta' => $meta];
		}

		return $out;
	}

	private function makeChunk(string $text, array $meta, int $index): array {
		$cmeta = $meta;
		$cmeta['chunk_index'] = $index;

		return [
			'id'   => uniqid('chunk_', true),
			'text' => trim($text),
			'meta' => $cmeta
		];
	}
}
