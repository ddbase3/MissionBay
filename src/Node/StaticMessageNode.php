<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;

class StaticMessageNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'staticmessagenode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'text',
				description: 'The static text message to output.',
				type: 'string',
				default: '',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'message',
				description: 'The resulting static message.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, IAgentContext $context): array {
		$text = $inputs['text'] ?? '';

		return ['message' => (string)$text];
	}

	public function getDescription(): string {
		return 'Outputs a static text message as provided in the input. Useful for sending fixed content into a flow, such as default values, templates, or predefined prompts.';
	}
}

