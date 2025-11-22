<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentChunker;
use MissionBay\Dto\AgentParsedContent;

/**
 * NoChunkerAgentResource
 *
 * Creates exactly one chunk from parsed content.
 * Useful when text is already small or pre-chunked.
 */
class NoChunkerAgentResource extends AbstractAgentResource implements IAgentChunker {

	public static function getName(): string {
		return 'nochunkeragentresource';
	}

	public function getDescription(): string {
		return 'Creates exactly one chunk from parsed content.';
	}

	public function getPriority(): int {
		return 999;
	}

	public function supports(AgentParsedContent $parsed): bool {
		return strlen($parsed->text) < 2000;
	}

	public function chunk(AgentParsedContent $parsed): array {
		return [
			[
				'id' => uniqid('chunk_', true),
				'text' => $parsed->text,
				'meta' => $parsed->metadata
			]
		];
	}
}
