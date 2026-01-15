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
	 * Scalars are returned as-is.
	 *
	 * @param array|string|int|float|bool|null $config
	 * @return mixed
	 */
	public function resolveValue(array|string|int|float|bool|null $config): mixed;
}
