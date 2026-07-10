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

namespace MissionBay\Resource\AgentTool\CurrentTime;

use MissionBay\Api\IAgentTool;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Resource\AbstractAgentResource;

/**
 * CurrentTimeAgentTool
 *
 * Simple tool that returns the current server time with timezone.
 * Exposed as OpenAI-compatible function tool.
 */
class CurrentTimeAgentTool extends AbstractAgentResource implements IAgentTool {

	public static function getName(): string {
		return 'currenttimeagenttool';
	}

	public function getDescription(): string {
		return 'Provides the current server time and timezone. Useful for scheduling and answering time-related questions.';
	}

	/**
	 * Returns OpenAI-compatible tool definition.
	 *
	 * @return array[]
	 */
	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Current Time Lookup',
			'category' => 'lookup',
			'tags' => ['date', 'time'],
			'priority' => 50,
			'function' => [
				'name' => 'get_current_time',
				'description' => 'Returns the current server time and date.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'timezone' => [
							'type' => 'string',
							'description' => 'Optional timezone in IANA format (e.g. Europe/Berlin). Defaults to server timezone.',
						]
					],
					'required' => []
				]
			]
		]];
	}

	/**
	 * Executes the tool call.
	 *
	 * @param string $toolName
	 * @param array $arguments
	 * @param IAgentContext $context
	 * @return array<string,mixed>
	 */
	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		if ($toolName !== 'get_current_time') {
			throw new \InvalidArgumentException("Unsupported tool: $toolName");
		}

		$tzName = $arguments['timezone'] ?? date_default_timezone_get();
		try {
			$tz = new \DateTimeZone($tzName);
		} catch (\Exception $e) {
			$tz = new \DateTimeZone(date_default_timezone_get());
		}

		$dt = new \DateTime('now', $tz);

		return [
			'time' => $dt->format('Y-m-d H:i:s'),
			'timezone' => $tz->getName()
		];
	}
}

