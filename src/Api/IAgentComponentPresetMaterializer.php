<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Api;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;

/**
 * Materializes one stored agent component preset into fresh configured runtime resources.
 */
interface IAgentComponentPresetMaterializer {

	/**
	 * Creates an agent context suitable for preset materialization and isolated tests.
	 *
	 * @param array<string,mixed> $vars
	 */
	public function createContext(array $vars = []): IAgentContext;

	/**
	 * Materializes one preset including recursive dock dependencies and capability wrappers.
	 */
	public function materialize(string $presetId, IAgentContext $context): AgentComponentPresetMaterialization;
}
