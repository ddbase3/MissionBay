<?php declare(strict_types=1);

namespace MissionBay\Api;

/**
 * IAgentSkillSelector
 *
 * Returns skill plans for the current request.
 * Multi-skill: return multiple plans if multiple skills should be active at the same time
 * (e.g. RAG + sendmail). The host will merge them into one effective plan.
 *
 * @return \MissionBay\Skill\SkillPlan[]
 */
interface IAgentSkillSelector {

	public function selectPlans(string $userPrompt, string $systemPrompt, IAgentContext $context): array;
}
