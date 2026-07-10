<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Profile;

use MissionBay\Api\IAgentAssistantToolSetupFactory;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentProfileSelector;
use MissionBay\Dto\Assistant\AgentAssistantToolSetup;

final class AgentAssistantToolSetupFactory implements IAgentAssistantToolSetupFactory {

	public function create(array $tools, ?IAgentProfileSelector $profileSelector, string $prompt, string $system, IAgentContext $context): AgentAssistantToolSetup {
		$effectivePlan = $this->buildEffectiveProfilePlan($profileSelector, $prompt, $system, $context);
		$filter = new ToolDefFilter();
		$filtered = $filter->filter($tools, $effectivePlan);
		$report = $filtered['report'];
		$profileWasUnavailable = false;
		$missingRequiredTools = [];

		if (!$report->isFeasible()) {
			$profileWasUnavailable = true;
			$missingRequiredTools = $report->getMissingRequiredTools();
			$effectivePlan = new ProfilePlan('default');
			$filtered = $filter->filter($tools, $effectivePlan);
			$report = $filtered['report'];
		}

		$allowedToolNames = $filtered['allowedToolNames'];
		$guardedTools = $tools;

		if (is_array($allowedToolNames) && count($allowedToolNames) > 0) {
			$guardedTools = array_map(
				fn($tool) => new ToolGuardAgentTool($tool, $allowedToolNames),
				$tools
			);
		}

		return new AgentAssistantToolSetup(
			tools: $guardedTools,
			toolDefs: $filtered['toolDefs'],
			effectivePlan: $effectivePlan,
			report: $report,
			allowedToolNames: $allowedToolNames,
			profileWasUnavailable: $profileWasUnavailable,
			missingRequiredTools: $missingRequiredTools
		);
	}

	private function buildEffectiveProfilePlan(?IAgentProfileSelector $profileSelector, string $prompt, string $system, IAgentContext $context): ProfilePlan {
		if (!$profileSelector instanceof IAgentProfileSelector) {
			return new ProfilePlan('default');
		}

		$plans = $profileSelector->selectPlans($prompt, $system, $context);
		if (count($plans) === 0) {
			return new ProfilePlan('default');
		}

		$mergedSystemAppend = [];
		$mergedAllowed = null;
		$mergedRequired = [];

		foreach ($plans as $plan) {
			if (!$plan instanceof ProfilePlan) {
				continue;
			}

			$append = $plan->getSystemAppend();
			if ($append !== null && trim($append) !== '') {
				$mergedSystemAppend[] = trim($append);
			}

			$allowed = $plan->getAllowedTools();
			if (is_array($allowed)) {
				if ($mergedAllowed === null) {
					$mergedAllowed = [];
				}

				foreach ($allowed as $name) {
					$name = (string)$name;
					if ($name !== '') {
						$mergedAllowed[$name] = true;
					}
				}
			}

			foreach ($plan->getRequiredTools() as $req) {
				$req = (string)$req;
				if ($req !== '') {
					$mergedRequired[$req] = true;
				}
			}
		}

		$effectiveAllowedTools = $mergedAllowed === null ? null : array_keys($mergedAllowed);
		if (is_array($effectiveAllowedTools)) {
			sort($effectiveAllowedTools);
		}

		$effectiveRequiredTools = array_keys($mergedRequired);
		sort($effectiveRequiredTools);

		return new ProfilePlan(
			profileName: 'effective',
			systemAppend: count($mergedSystemAppend) > 0 ? implode("

", $mergedSystemAppend) : null,
			allowedTools: $effectiveAllowedTools,
			requiredTools: $effectiveRequiredTools
		);
	}
}
