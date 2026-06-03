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

namespace MissionBay\Display;

final class LlmConfigDisplay extends AbstractServiceConfigDisplay {

	private const SETTINGS_GROUP = 'service-llm';
	private const CONNECTION_GROUP = 'connection';
	private const SERVICE_TYPE = 'llm';

	public static function getName(): string {
		return 'llmconfigdisplay';
	}

	public function getHelp(): string {
		return 'Configure LLM services stored in settings group "service-llm".';
	}

	protected function getSettingsGroup(): string {
		return self::SETTINGS_GROUP;
	}

	protected function getConnectionGroup(): string {
		return self::CONNECTION_GROUP;
	}

	protected function getServiceType(): string {
		return self::SERVICE_TYPE;
	}

	protected function getTemplate(): string {
		return 'Display/LlmConfigDisplay.php';
	}

	protected function getInstancePrefix(): string {
		return 'llmcfg';
	}

	protected function getListDataKey(): string {
		return 'llms';
	}

	protected function getSingleDataKey(): string {
		return 'llm';
	}

	protected function getMissingIdMessage(): string {
		return 'Missing LLM id.';
	}

	protected function getMissingNameMessage(): string {
		return 'Missing LLM name.';
	}

	protected function getMissingDriverMessage(): string {
		return 'Missing service driver.';
	}

	protected function getUnknownDriverMessage(string $driver): string {
		return 'Unknown LLM service driver: ' . $driver;
	}

	protected function getMissingModelMessage(): string {
		return 'Missing model.';
	}

	protected function readSpecificOptions(array $options): array {
		$options = $this->removeRuntimeOptionAliases($options);

		$temperature = $this->readOptionalFloat('temperature', 'Temperature');
		$maxTokens = $this->readOptionalInt('maxTokens', 'Max tokens');
		$topP = $this->readOptionalFloat('topP', 'Top P');
		$timeoutSeconds = $this->readOptionalInt('timeoutSeconds', 'Timeout seconds');
		$connectTimeoutSeconds = $this->readOptionalInt('connectTimeoutSeconds', 'Connect timeout seconds');

		if($temperature !== null) {
			$options['temperature'] = $temperature;
		}

		if($maxTokens !== null) {
			$options['maxTokens'] = $maxTokens;
		}

		if($topP !== null) {
			$options['topP'] = $topP;
		}

		if($timeoutSeconds !== null) {
			$options['timeoutSeconds'] = $timeoutSeconds;
		}

		if($connectTimeoutSeconds !== null) {
			$options['connectTimeoutSeconds'] = $connectTimeoutSeconds;
		}

		return $options;
	}

	protected function expandSpecificDisplayOptions(array $row): array {
		$options = is_array($row['options'] ?? null) ? $row['options'] : [];
		$options = $this->removeRuntimeOptionAliases($options);

		$row['options'] = $options;
		$row['temperature'] = $this->normalizeNullableNumber($options['temperature'] ?? null);
		$row['maxTokens'] = $this->normalizeNullableNumber($options['maxTokens'] ?? null);
		$row['topP'] = $this->normalizeNullableNumber($options['topP'] ?? null);
		$row['timeoutSeconds'] = $this->normalizeNullableNumber($options['timeoutSeconds'] ?? null);
		$row['connectTimeoutSeconds'] = $this->normalizeNullableNumber($options['connectTimeoutSeconds'] ?? null);

		return $row;
	}

	private function removeRuntimeOptionAliases(array $options): array {
		unset(
			$options['max_tokens'],
			$options['top_p'],
			$options['timeout_seconds'],
			$options['connect_timeout_seconds'],
			$options['maxtokens']
		);

		return $options;
	}
}
