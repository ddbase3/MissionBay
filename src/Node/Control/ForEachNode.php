<?php declare(strict_types=1);

namespace MissionBay\Node\Control;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class ForEachNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'foreachnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'items',
				description: 'The input array to iterate over.',
				type: 'array',
				required: true
			),
			new AgentNodePort(
				name: 'node',
				description: 'The node instance to be executed for each item.',
				type: IAgentNode::class,
				required: true
			),
			new AgentNodePort(
				name: 'inputMap',
				description: 'Associative array mapping node inputs to expressions like $item, $key, $index, or $context_foo.',
				type: 'array<string>',
				default: [],
				required: false
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'results',
				description: 'Array of results from each node execution.',
				type: 'array',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Optional error message if input or execution failed.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {
		$items = $inputs['items'] ?? null;
		$node = $inputs['node'] ?? null;
		$inputMap = $inputs['inputMap'] ?? [];

		if (!is_array($items)) {
			return ['error' => $this->error('Input "items" must be an array')];
		}

		if (!$node instanceof IAgentNode) {
			return ['error' => $this->error('Input "node" must be an instance of IAgentNode')];
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
						$varName = substr($expr, 1); // "$context_foo" → "context_foo"
						$mappedInputs[$target] = $context->getVar($varName);
					} else {
						$mappedInputs[$target] = null;
					}
				}

				// Fallback-Zuweisung, wenn nicht durch inputMap gesetzt
				if (!isset($mappedInputs['item'])) {
					$mappedInputs['item'] = is_array($item)
						? array_diff_key($item, ['flow' => true]) // 🛡️ Flow-Objekt aus item entfernen
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
				$results[] = ['error' => $this->error($e->getMessage())];
			}

			$index++;
		}

		return ['results' => $results];
	}

	public function getDescription(): string {
		return 'Executes a given node once for each item in an input array. Supports dynamic input mapping using placeholders like $item, $key, $index, and $context_* variables. Returns a list of results.';
	}
}

