<?php declare(strict_types=1);

namespace MissionBay\Skill;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentSkillSelector;

final class NoOpSkillSelector implements IAgentSkillSelector {

	public static function getName(): string {
		return 'noopskillselector';
	}

	public function selectPlans(string $userPrompt, string $systemPrompt, IAgentContext $context): array {
		return [
			new SkillPlan(
				skillName: 'default',
				systemAppend: null,
				allowedTools: null,
				requiredTools: []
			)
		];
	}
}
