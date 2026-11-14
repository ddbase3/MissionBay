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
