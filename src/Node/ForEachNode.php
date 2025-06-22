<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class ForEachNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'foreachnode';
	}

	public function getInputDefinitions(): array {
		return ['items', 'node', 'inputMap'];
	}

	public function getOutputDefinitions(): array {
		return ['results', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$items = $inputs['items'] ?? null;
		$node = $inputs['node'] ?? null;
		$inputMap = $inputs['inputMap'] ?? [];

		if (!is_array($items)) {
			return ['error' => 'Input "items" must be an array'];
		}

		if (!$node instanceof IAgentNode) {
			return ['error' => 'Input "node" must be an instance of IAgentNode'];
		}

		$results = [];
		$index = 0;

		foreach ($items as $key => $item) {
			try {
				$mappedInputs = [];

				foreach ($inputMap as $target => $expr) {
					if ($expr === '$item') {
						$mappedInputs[$target] = $item;
					} elseif ($expr === '$key') {
						$mappedInputs[$target] = $key;
					} elseif ($expr === '$index') {
						$mappedInputs[$target] = $index;
					} elseif (str_starts_with($expr, '$context_')) {
						$varName = substr($expr, 1); // '$context_flow' → 'context_flow'
						$mappedInputs[$target] = $context->getVar($varName);
					} else {
						$mappedInputs[$target] = null;
					}
				}

				// Default values, wenn nicht explizit gesetzt
				if (!isset($mappedInputs['item'])) {
					$mappedInputs['item'] = is_array($item)
						? array_diff_key($item, ['flow' => true]) // 🛡️ Schutz vor rekursivem flow
						: $item;
				}
				if (!isset($mappedInputs['key'])) {
					$mappedInputs['key'] = $key;
				}
				if (!isset($mappedInputs['index'])) {
					$mappedInputs['index'] = $index;
				}

				$results[] = $node->execute($mappedInputs, $context);
			} catch (\Throwable $e) {
				$results[] = ['error' => $e->getMessage()];
			}

			$index++;
		}

		return ['results' => $results];
	}
}

