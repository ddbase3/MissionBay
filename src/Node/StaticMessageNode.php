<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Agent\AgentContext;

class StaticMessageNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'staticmessagenode';
	}

	public function getInputDefinitions(): array {
		return ['text']; 
	}

	public function getOutputDefinitions(): array {
		return ['message'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$text = $inputs['text'] ?? '';

		return ['message' => (string)$text];
	}

	public function getDescription(): string {
		return 'Outputs a static text message as provided in the input. Useful for sending fixed content into a flow, such as default values, templates, or predefined prompts.';
	}
}

