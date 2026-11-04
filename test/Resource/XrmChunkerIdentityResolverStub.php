<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use MissionBay\Api\IAgentConfigValueResolver;

final class XrmChunkerIdentityResolverStub implements IAgentConfigValueResolver {

	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		if (is_array($config) && ($config['mode'] ?? '') === 'fixed') {
			return $config['value'] ?? null;
		}

		return $config;
	}
}
