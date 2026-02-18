<?php declare(strict_types=1);

namespace MissionBay\Skill;

final class SkillPlan {

	/**
	 * @param string[]|null $allowedTools If null, no filtering is applied.
	 * @param string[] $requiredTools Tools that must be available, otherwise plan is not feasible.
	 */
	public function __construct(
		private string $skillName,
		private ?string $systemAppend = null,
		private ?array $allowedTools = null,
		private array $requiredTools = []
	) {
	}

	public function getSkillName(): string {
		return $this->skillName;
	}

	public function getSystemAppend(): ?string {
		return $this->systemAppend;
	}

	/**
	 * @return string[]|null
	 */
	public function getAllowedTools(): ?array {
		return $this->allowedTools;
	}

	/**
	 * @return string[]
	 */
	public function getRequiredTools(): array {
		return $this->requiredTools;
	}
}
