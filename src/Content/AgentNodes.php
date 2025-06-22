<?php declare(strict_types=1);

namespace MissionBay\Content;

use Base3\Api\IClassMap;
use Base3\Api\IOutput;
use MissionBay\Api\IAgentNode;

class AgentNodes implements IOutput {

        public function __construct(private readonly IClassMap $classmap) {}

        // Implementation of IBase

        public static function getName(): string {
                return 'agentnodes';
        }

        // Implementation of IOutput

	public function getOutput($out = 'html') {
		if ($out != 'json') return '';

		$agentNodes = $this->classmap->getInstancesByInterface(IAgentNode::class);

		$result = [];
		foreach ($agentNodes as $agentNode) {
			$result[] = [
				'name' => $agentNode::getName(),
				'class' => get_class($agentNode),
				'description' => $agentNode->getDescription(),
				'inputs' => $agentNode->getInputDefinitions(),
				'outputs' => $agentNode->getOutputDefinitions()
			];
		}

		return json_encode($result, JSON_PRETTY_PRINT);
	}

        public function getHelp() {
                return 'Help of AgentNodes' . "\n";
        }
}

