<?php declare(strict_types=1);

namespace MissionBay\Node\Control;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class SubFlowNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'subflownode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'flow',
				description: 'An instance of IAgentFlow to be executed as subflow.',
				type: IAgentFlow::class,
				required: true
			)
			// weitere Inputs werden dynamisch übergeben (z. B. "item", "context_...")
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: '*',
				description: 'Dynamic outputs, forwarded from the subflow result.',
				type: 'mixed',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {
		$flow = isset($inputs['flow']) && $inputs['flow'] instanceof IAgentFlow
			? clone $inputs['flow']
			: null;

		if (!$flow instanceof IAgentFlow) {
			return ['error' => $this->error('Missing or invalid flow input')];  // TODO: check if output node necessary
		}

		$flow->setContext($context);
		unset($inputs['flow']);

		// Falls 'item' ein Array ist und 'flow' darin enthalten ist – auch entfernen
		if (isset($inputs['item']) && is_array($inputs['item']) && array_key_exists('flow', $inputs['item'])) {
			unset($inputs['item']['flow']);
		}

		$allOutputs = $flow->run($inputs);

		foreach ($allOutputs as $partial) {
			if (is_array($partial)) {
				return $partial;
			}
		}

		return [];
	}

	public function getDescription(): string {
		return 'Executes a nested AgentFlow within the current flow using the given inputs. Returns the first non-null result from the subflow. Useful for modularization, reuse, or encapsulation of complex logic.';
	}
}

