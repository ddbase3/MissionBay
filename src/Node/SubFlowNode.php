<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;
use MissionBay\Agent\AgentFlow;

class SubFlowNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'subflownode';
	}

	public function getInputDefinitions(): array {
		return ['flow'];
	}

	public function getOutputDefinitions(): array {
		return ['*'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$flow = $inputs['flow'] ?? null;

		if (!$flow instanceof AgentFlow) {
			return ['error' => 'Missing or invalid flow input'];
		}

		// Entferne flow aus dem Input
		unset($inputs['flow']);

		// 🛡️ Zusätzlicher Schutz, falls 'item' selbst ein Array mit 'flow' ist
		if (isset($inputs['item']) && is_array($inputs['item']) && array_key_exists('flow', $inputs['item'])) {
			unset($inputs['item']['flow']);
		}

		$allOutputs = $flow->run($inputs, $context);

		foreach ($allOutputs as $partial) {
			if (is_array($partial)) {
				return $partial;
			}
		}

		return [];
	}
}

