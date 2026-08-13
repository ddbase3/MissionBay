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

interface IConfiguredParserServiceResolver {

	/**
	 * @return array<int,string>
	 */
	public function listServiceIds(): array;

	public function getPriority(string $serviceId): int;

	/**
	 * @param array<string,mixed> $optionOverrides
	 */
	public function resolve(string $serviceId, array $optionOverrides = []): IParserService;

	/**
	 * Resolves a transient parser service configuration without persisting it.
	 * Intended for configuration validation and end-to-end service tests.
	 *
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> $optionOverrides
	 */
	public function resolveSettings(string $serviceId, array $settings, array $optionOverrides = []): IParserService;
}
