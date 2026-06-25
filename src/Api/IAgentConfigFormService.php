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

use Base3\Api\IMvcView;

interface IAgentConfigFormService {

	/**
	 * Returns the default settings for the reusable agent configuration block.
	 *
	 * @return array<string,mixed>
	 */
	public function getDefaultSettings(): array;

	/**
	 * Normalizes stored agent configuration settings.
	 *
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	public function normalizeSettings(array $settings): array;

	/**
	 * Reads and validates agent configuration values from the current request.
	 *
	 * @param array<int,string> $errors
	 * @return array<string,mixed>
	 */
	public function getPostedSettings(array &$errors): array;

	/**
	 * Reads agent configuration values from the current request for redisplay.
	 *
	 * @return array<string,mixed>
	 */
	public function getPostedViewValues(): array;

	/**
	 * Converts stored agent settings into template values.
	 *
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	public function settingsToViewValues(array $settings): array;

	/**
	 * Assigns all template values required by the reusable agent configuration block.
	 *
	 * @param array<string,mixed> $values
	 * @param array<string,mixed> $options
	 */
	public function assignViewData(IMvcView $view, array $values, array $options = []): void;

}
