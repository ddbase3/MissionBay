<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Orchestrator\Fixture;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\MissionBay\Api\IAgentTool;

final class AgentDelegationCountTool implements IAgentTool {

	public int $calls = 0;

	public static function getName(): string {
		return 'agentdelegationcounttool';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'count_once',
				'description' => 'Records a single harmless test operation.',
				'parameters' => ['type' => 'object', 'properties' => []]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		if ($name !== 'count_once') {
			throw new \InvalidArgumentException('Unexpected test tool call.');
		}
		$this->calls++;
		return ['count' => $this->calls];
	}
}
