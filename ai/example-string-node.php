<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;

class StringReverserNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'stringreversernode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'text',
				description: 'The input string to be reversed.',
				type: 'string',
				default: '',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'reversed',
				description: 'The reversed result of the input string.',
				type: 'string',
				default: null,
				required: false
			)
		];
	}

	public function execute(array $inputs, IAgentContext $context): array {
		$text = $inputs['text'] ?? '';
		$reversed = strrev($text);

		return ['reversed' => $reversed];
	}

	public function getDescription(): string {
		return 'Reverses the given input string and returns the result. Useful for string manipulation, testing, or flow demonstrations.';
	}
}

