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

namespace MissionBay\Agent;

use Base3\ConfigValue\Api\IConfigValueResolver;
use MissionBay\Api\IAgentConfigValueResolver;
use RuntimeException;

/**
 * Resolves MissionBay agent configuration values at runtime.
 *
 * Generic config value modes such as fixed, configuration, env and file are
 * delegated to the BASE3 config value resolver. MissionBay-specific runtime
 * modes remain here to avoid exposing them as generic framework modes.
 *
 * Supported MissionBay-specific modes:
 * - inherit
 * - random
 * - uuid
 */
class AgentConfigValueResolver implements IAgentConfigValueResolver {

	public function __construct(
		private readonly IConfigValueResolver $configValueResolver
	) {}

	/**
	 * Resolves a config entry into a final runtime value.
	 *
	 * Scalars and generic config value definitions are delegated to the BASE3
	 * config value resolver. Only MissionBay-specific modes are handled locally.
	 *
	 * @param array|string|int|float|bool|null $config Raw agent config value definition
	 * @return mixed Resolved runtime value
	 */
	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		if (is_array($config)) {
			$mode = $config['mode'] ?? null;

			switch ($mode) {
				case 'inherit':
					return null;

				case 'random':
					return $this->resolveRandom($config);

				case 'uuid':
					return $this->generateUuidV4();
			}
		}

		return $this->configValueResolver->resolve($config);
	}

	/**
	 * Resolves a random value from the configured value list.
	 *
	 * The legacy field "value" is still supported. The field "values" is also
	 * accepted as a clearer alternative for future definitions.
	 *
	 * @param array $config Random config value definition
	 * @return mixed Randomly selected value or null if no values are available
	 */
	protected function resolveRandom(array $config): mixed {
		$values = $config['values'] ?? ($config['value'] ?? null);

		if (!is_array($values) || empty($values)) {
			return null;
		}

		return $values[array_rand($values)];
	}

	/**
	 * Generates a UUID v4 string.
	 *
	 * @return string UUID v4 value
	 */
	protected function generateUuidV4(): string {
		$data = random_bytes(16);
		$data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
		$data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
}
