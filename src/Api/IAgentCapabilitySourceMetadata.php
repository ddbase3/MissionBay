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

/**
 * Stable metadata used when a tool is presented as one selectable capability
 * source to an agent. The description must describe the underlying tool
 * itself, not the configured wrapper around it.
 */
interface IAgentCapabilitySourceMetadata {

	public function getCapabilitySourceId(): string;

	public function getCapabilitySourceLabel(): string;

	public function getCapabilitySourceDescription(): string;
}
