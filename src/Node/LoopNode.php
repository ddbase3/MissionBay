<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class LoopNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'loopnode';
	}

	public function getInputDefinitions(): array {
		return ['count', 'node', 'inputMap'];
	}

	public function getOutputDefinitions(): array {
		return ['results', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$count = $inputs['count'] ?? null;
		$node = $inputs['node'] ?? null;
		$inputMap = $inputs['inputMap'] ?? [];

		if (!is_int($count) || $count < 0 || $count > 1000) {
			return ['error' => 'LoopNode: "count" must be an integer between 0 and 1000'];
		}

		if (!$node instanceof IAgentNode) {
			return ['error' => 'LoopNode: "node" must be a valid node'];
		}

		$results = [];

		for ($i = 0; $i < $count; $i++) {
			try {
				$mappedInputs = [];

				foreach ($inputMap as $target => $expr) {
					if ($expr === '$index') {
						$mappedInputs[$target] = $i;
					} elseif (str_starts_with($expr, '$context_')) {
						$varName = substr($expr, 1);
						$mappedInputs[$target] = $context->getVar($varName);
					} else {
						$mappedInputs[$target] = null;
					}
				}

				// Standard-Input `index`
				if (!isset($mappedInputs['index'])) {
					$mappedInputs['index'] = $i;
				}

				$results[] = $node->execute($mappedInputs, $context);
			} catch (\Throwable $e) {
				$results[] = ['error' => $e->getMessage()];
			}
		}

		return ['results' => $results];
	}
}

