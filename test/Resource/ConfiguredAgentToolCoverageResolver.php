<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use MissionBay\Api\IAgentConfigValueResolver;

final class ConfiguredAgentToolCoverageResolver implements IAgentConfigValueResolver {

	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		return $config;
	}
}
