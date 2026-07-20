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

namespace MissionBay\Capability;

use AssistantFoundation\Api\IAgentCapabilitySelector;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelection;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySelectionRequest;

/**
 * Backward-compatible deterministic selector adapter.
 *
 * Capability selection algorithms are now chosen through explicit stages.
 * New code should inject HybridAgentCapabilitySelector into
 * capability-selection or SemanticAgentCapabilitySelector into
 * ai-capability-selection directly.
 */
final class ProfileAwareAgentCapabilitySelector implements IAgentCapabilitySelector {

	public function __construct(
		private readonly HybridAgentCapabilitySelector $hybridSelector
	) {}

	public function select(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionRequest $request
	): AgentCapabilitySelection {
		$config = $request->getConfig();
		if ($config->getStrategy() !== AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC) {
			return $this->hybridSelector->select($catalog, $request);
		}

		$data = $config->toArray();
		$data['strategy'] = AgentCapabilitySelectionConfig::STRATEGY_HYBRID;

		return $this->hybridSelector->select(
			$catalog,
			new AgentCapabilitySelectionRequest(
				iteration: $request->getIteration(),
				contextText: $request->getContextText(),
				config: AgentCapabilitySelectionConfig::fromArray($data),
				previousSelectedToolNames: $request->getPreviousSelectedToolNames(),
				recentToolNames: $request->getRecentToolNames(),
				requiredToolNames: $request->getRequiredToolNames(),
				model: $request->getModel()
			)
		);
	}
}
