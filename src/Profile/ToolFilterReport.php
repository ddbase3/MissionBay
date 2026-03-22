<?php declare(strict_types=1);

namespace MissionBay\Profile;

final class ToolFilterReport {

	/**
	 * @param string[] $missingRequiredTools
	 */
	public function __construct(
		private array $missingRequiredTools
	) {
	}

	/**
	 * @return string[]
	 */
	public function getMissingRequiredTools(): array {
		return $this->missingRequiredTools;
	}

	public function isFeasible(): bool {
		return count($this->missingRequiredTools) === 0;
	}
}
