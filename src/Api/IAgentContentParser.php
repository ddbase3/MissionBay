<?php declare(strict_types=1);

namespace MissionBay\Api;

/**
 * Parsers convert raw content into a normalized structure.
 * Each parser declares a priority; lowest value = highest priority.
 */
interface IAgentContentParser {

	/**
	 * Defines parser priority.
	 * Lower numbers mean earlier execution.
	 */
	public function getPriority(): int;

	/**
	 * Returns true if this parser can parse the content.
	 */
	public function supports(mixed $content): bool;

	/**
	 * Parses the content and returns a normalized structure:
	 * [
	 * 	 'text' => string,
	 * 	 'meta' => array<string,mixed>
	 * ]
	 */
	public function parse(mixed $content): array;
}
