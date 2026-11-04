<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentTool;

final class ProfileToolsTestAgentTool implements IAgentTool {

	/** @param array<int,array<string,mixed>> $definitions */
	public function __construct(private readonly array $definitions = []) {}

	public static function getName(): string {
		return 'profiletoolstestagenttool';
	}

	public function getToolDefinitions(): array {
		return $this->definitions;
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return ['name' => $name, 'arguments' => $arguments];
	}
}
