<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\LoggerResource;
use MissionBay\Api\IAgentConfigValueResolver;
use Base3\Logger\Api\ILogger;

final class LoggerResourceTestDouble1 implements IAgentConfigValueResolver {
			private ?string $resolvedScope;

			public function __construct(?string $resolvedScope) {
				$this->resolvedScope = $resolvedScope;
			}

			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				// For tests we return the injected resolved scope (or null),
				// independent from $config shape.
				return $this->resolvedScope;
			}
		}
