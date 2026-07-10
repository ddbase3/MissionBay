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

namespace MissionBay\Api;

use AssistantFoundation\Api\IAgentContext;

use Base3\Api\IBase;

/**
 * IAgentPromptProvider
 *
 * Provides reusable agent prompt templates. Prompts describe suggested model
 * workflows and can be exposed by transports such as MCP without becoming
 * transport-specific themselves.
 */
interface IAgentPromptProvider extends IBase {

	/**
	 * Returns prompt definitions exposed by this provider.
	 *
	 * Each prompt should contain at least name and description.
	 *
	 * @param IAgentContext $context
	 * @return array<int, array<string, mixed>>
	 */
	public function getPromptDefinitions(IAgentContext $context): array;

	/**
	 * Returns one prompt by name and arguments.
	 *
	 * Return null when this provider does not own the prompt name.
	 *
	 * @param string $name
	 * @param array<string, mixed> $arguments
	 * @param IAgentContext $context
	 * @return array<string, mixed>|null
	 */
	public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array;

}
