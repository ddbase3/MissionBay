<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use Base3\Api\ISchemaProvider;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Resource\AgentContext\Text\StaticTextContextAgentResource;
use PHPUnit\Framework\TestCase;

final class StaticTextContextAgentResourceTestDouble1 implements IAgentConfigValueResolver {
			/**
			 * @param array<string,mixed> $map
			 */
			public function __construct(private readonly array $map) {}

			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				$key = is_array($config) ? (string)json_encode($config) : (string)$config;

				return array_key_exists($key, $this->map) ? $this->map[$key] : $config;
			}
		}
