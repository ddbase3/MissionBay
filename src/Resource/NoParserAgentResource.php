<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentContentParser;

/**
 * NoParserAgentResource
 *
 * Minimal parser that simply forwards plain text content.
 * Useful when content extractors already yield clean text.
 */
class NoParserAgentResource extends AbstractAgentResource implements IAgentContentParser {

	public static function getName(): string {
		return 'noparseragentresource';
	}

	public function getDescription(): string {
		return 'Pass-through parser for already-parsed text content.';
	}

	public function getPriority(): int {
		return 999;
	}

	public function supports(mixed $content): bool {
		return is_string($content);
	}

	public function parse(mixed $content): array {
		return [
			'text' => trim((string)$content),
			'meta' => []
		];
	}
}
