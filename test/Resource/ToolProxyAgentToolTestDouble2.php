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

namespace Base3\MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Dto\AgentExecutionEvent;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Resource\ToolProxyAgentTool;

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
