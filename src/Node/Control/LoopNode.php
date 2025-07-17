<?php declare(strict_types=1);

namespace MissionBay\Node\Control;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class LoopNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'loopnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'count',
				description: 'Number of times to execute the inner node (0–1000).',
				type: 'int',
				required: true
			),
			new AgentNodePort(
				name: 'node',
				description: 'The node to execute in each iteration.',
				type: IAgentNode::class,
				required: true
			),
			new AgentNodePort(
				name: 'inputMap',
				description: 'Associative array mapping input names to placeholders like $index or $context_*.',
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
				description: 'Array of outputs returned by each iteration.',
				type: 'array',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if input is invalid or loop execution fails.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$count = $inputs['count'] ?? null;
		$node = $inputs['node'] ?? null;
		$inputMap = $inputs['inputMap'] ?? [];

		if (!is_int($count) || $count < 0 || $count > 1000) {
			return ['error' => $this->error('LoopNode: "count" must be an integer between 0 and 1000')];
		}

		if (!$node instanceof IAgentNode) {
			return ['error' => $this->error('LoopNode: "node" must be a valid node')];
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

				if (!isset($mappedInputs['index'])) {
					$mappedInputs['index'] = $i;
				}

				$results[] = $node->execute($mappedInputs, $context);
			} catch (\Throwable $e) {
				$results[] = ['error' => $this->error($e->getMessage())];
			}
		}

		return ['results' => $results];
	}

	public function getDescription(): string {
		return 'Executes a given node a fixed number of times. Supports dynamic input mapping with $index and $context_* placeholders. Useful for generating repeated structures, testing, or iterative processing.';
	}
}

