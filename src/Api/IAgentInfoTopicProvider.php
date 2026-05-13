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

use Base3\Api\IBase;
use MissionBay\Dto\AgentInfoRequest;
use MissionBay\Dto\AgentInfoResult;

/**
 * IAgentInfoTopicProvider
 *
 * Provides read-only information for one logical info topic.
 *
 * Implementations are discovered through the BASE3 class map by this interface.
 * The central info tool stays generic and delegates all domain-specific lookup
 * logic to these providers.
 */
interface IAgentInfoTopicProvider extends IBase {

	/**
	 * Returns the primary technical topic handled by this provider.
	 *
	 * Examples:
	 * - course
	 * - user
	 * - cron
	 * - plugin
	 *
	 * @return string
	 */
	public function getTopic(): string;

	/**
	 * Returns additional topic aliases handled by this provider.
	 *
	 * Aliases should be lowercase technical tokens.
	 *
	 * @return array<int, string>
	 */
	public function getTopicAliases(): array;

	/**
	 * Returns a short human-readable topic title.
	 *
	 * @return string
	 */
	public function getTitle(): string;

	/**
	 * Returns a compact description of what this provider can inspect.
	 *
	 * @return string
	 */
	public function getDescription(): string;

	/**
	 * Returns the provider priority.
	 *
	 * If multiple providers support the same topic, the highest priority wins.
	 *
	 * @return int
	 */
	public function getPriority(): int;

	/**
	 * Returns true if this provider supports the normalized topic.
	 *
	 * @param string $topic
	 * @return bool
	 */
	public function supports(string $topic): bool;

	/**
	 * Handles a read-only info request.
	 *
	 * Providers must never modify data, change configuration, trigger actions,
	 * or expose raw exceptions.
	 *
	 * @param AgentInfoRequest $request
	 * @return AgentInfoResult
	 */
	public function handle(AgentInfoRequest $request): AgentInfoResult;
}
