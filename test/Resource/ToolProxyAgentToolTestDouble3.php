<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionEvent;
use MissionBay\Api\IAgentTool;
use MissionBay\Resource\ToolProxyAgentTool;

final class ToolProxyAgentToolTestDouble3 implements IAgentTool  {
			public static function getName(): string { return 'failtool'; }
			public function getDescription(): string { return 'Fails.'; }
			public function getToolDefinitions(): array {
				return [[
					'type' => 'function',
					'label' => 'Fail',
					'category' => 'memory',
					'tags' => ['prefs'],
					'priority' => 10,
					'function' => [
						'name' => 'fail',
						'description' => 'Fails.',
						'parameters' => ['type' => 'object', 'properties' => [], 'required' => []]
					]
				]];
			}
			public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
				throw new \RuntimeException('boom');
			}
		}
