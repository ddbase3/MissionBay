<?php declare(strict_types=1);

namespace MissionBay\Api;

/**
 * Interface for resolving dynamic configuration values at runtime.
 * Supports various resolution strategies (fixed, env, config, etc).
 */
interface IAgentConfigValueResolver {

	/**
	 * Resolve a value from a config specification.
	 *
	 * @param array|string|null $config The configuration entry to resolve (can be raw or structured)
	 * @return mixed The resolved value (string, int, array, etc.)
	 */
	public function resolveValue(array|string|null $config): mixed;
}

