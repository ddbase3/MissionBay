<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentChunker;
use MissionBay\Dto\AgentParsedContent;

/**
 * NoChunkerAgentResource
 *
 * Creates exactly one chunk from small plain-text content.
 */
class NoChunkerAgentResource extends AbstractAgentResource implements IAgentChunker {

	public static function getName(): string {
		return 'nochunkeragentresource';
	}

	public function getDescription(): string {
		return 'Creates exactly one chunk from parsed plain-text content.';
	}

	public function getPriority(): int {
		return 999;
	}

	public function supports(AgentParsedContent $parsed): bool {
		if (!is_string($parsed->text)) {
			return false;
		}

		$text = trim($parsed->text);

		if ($text === '') {
			return false;
		}

		return strlen($text) < 2000;
	}

	public function chunk(AgentParsedContent $parsed): array {
		$text = trim($parsed->text ?? '');

		return [
			[
				'id'   => uniqid('chunk_', true),
				'text' => $text,
				'meta' => $parsed->metadata
			]
		];
	}
}
