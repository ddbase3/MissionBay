<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionEvent;
use MissionBay\Api\IAgentTool;
use MissionBay\Resource\ToolProxyAgentTool;

final class ToolProxyAgentToolTestDouble2 implements IAgentTool {
			private string $fnName;
			private string $category;
			private array $tags;
			private int $priority;
			private mixed $result;

			public function __construct(string $fnName, string $category, array $tags, int $priority, mixed $result) {
				$this->fnName = $fnName;
				$this->category = $category;
				$this->tags = $tags;
				$this->priority = $priority;
				$this->result = $result;
			}

			public static function getName(): string {
				return 'dummytool';
			}

			public function getDescription(): string {
				return 'Dummy tool for ToolProxyAgentTool tests.';
			}

			public function getToolDefinitions(): array {
				return [[
					'type' => 'function',
					'label' => 'Dummy',
					'category' => $this->category,
					'tags' => $this->tags,
					'priority' => $this->priority,
					'function' => [
						'name' => $this->fnName,
						'description' => 'Does something.',
						'parameters' => [
							'type' => 'object',
							'properties' => [
								'x' => ['type' => 'string']
							],
							'required' => []
						]
					]
				]];
			}

			public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
				if ($name !== $this->fnName) {
					throw new \InvalidArgumentException('Unexpected tool call: ' . $name);
				}
				return $this->result;
			}
		}
