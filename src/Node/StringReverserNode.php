<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentMemory;
use MissionBay\Agent\AgentContext;

class StringReverserNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'stringreversernode';
	}

	public function getInputDefinitions(): array {
		return ['text'];
	}

	public function getOutputDefinitions(): array {
		return ['reversed'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$text = $inputs['text'] ?? '';
		$reversed = strrev($text);

		return ['reversed' => $reversed];
	}

	public function getDescription(): string {
		return 'Reverses the given input string and returns the result. Useful for string manipulation, testing, or flow demonstrations.';
	}
}

