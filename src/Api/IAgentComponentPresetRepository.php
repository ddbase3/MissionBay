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
 * IAgentComponentPresetRepository
 *
 * Stores reusable component presets for agent tools, memories and resources.
 */
interface IAgentComponentPresetRepository {

	/**
	 * Returns all configured component presets indexed by preset id.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function getPresets(): array;

	/**
	 * Returns one configured component preset.
	 *
	 * @param string $id
	 * @param array<string,mixed> $default
	 * @return array<string,mixed>
	 */
	public function getPreset(string $id, array $default = []): array;

	/**
	 * Checks whether a preset exists.
	 *
	 * @param string $id
	 */
	public function hasPreset(string $id): bool;

	/**
	 * Stores one component preset.
	 *
	 * @param string $id
	 * @param array<string,mixed> $preset
	 */
	public function savePreset(string $id, array $preset): void;

	/**
	 * Removes one component preset.
	 *
	 * @param string $id
	 */
	public function removePreset(string $id): void;
}
