<?php declare(strict_types=1);

namespace MissionBay\Api;

/**
 * IAgentProfileSelector
 *
 * Returns profile plans for the current request.
 * Multi-profile: return multiple plans if multiple profiles should be active at the same time
 * (e.g. RAG + sendmail). The host will merge them into one effective plan.
 *
 * @return \MissionBay\Profile\ProfilePlan[]
 */
interface IAgentProfileSelector {

	public function selectPlans(string $userPrompt, string $systemPrompt, IAgentContext $context): array;
}
