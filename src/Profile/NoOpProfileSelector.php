<?php declare(strict_types=1);

namespace MissionBay\Profile;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentProfileSelector;

final class NoOpProfileSelector implements IAgentProfileSelector {

	public static function getName(): string {
		return 'noopprofileselector';
	}

	public function selectPlans(string $userPrompt, string $systemPrompt, IAgentContext $context): array {
		return [
			new ProfilePlan(
				profileName: 'default',
				systemAppend: null,
				allowedTools: null,
				requiredTools: []
			)
		];
	}
}
