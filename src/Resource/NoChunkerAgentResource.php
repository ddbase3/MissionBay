<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentChunker;

/**
 * NoChunkerAgentResource
 *
 * Creates a single chunk from the parsed text.
 * Useful when text is already short or pre-chunked.
 */
class NoChunkerAgentResource extends AbstractAgentResource implements IAgentChunker {

	public static function getName(): string {
		return 'nochunkeragentresource';
	}

	public function getDescription(): string {
		return 'Creates exactly one chunk from parsed text.';
	}

	public function getPriority(): int {
		return 999;
	}

	public function supports(array $parsed): bool {
		return isset($parsed['text']) && strlen($parsed['text']) < 2000;
	}

	public function chunk(array $parsed): array {
		return [
			[
				'id' => uniqid('chunk_', true),
				'text' => $parsed['text'],
				'meta' => $parsed['meta'] ?? []
			]
		];
	}
}
