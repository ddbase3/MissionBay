<?php declare(strict_types=1);

namespace MissionBay\Test\Resource\AgentTool\Agent;

use MissionBay\Api\IAgentConfigValueResolver;

final class RunConfiguredAgentTestConfigResolver implements IAgentConfigValueResolver {

	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		return $config;
	}
}
