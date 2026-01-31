<?php declare(strict_types=1);

namespace MissionBay\Content;

use Base3\Api\IClassMap;
use Base3\Api\IOutput;
use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentNodePort;

class AgentNodes implements IOutput {

	public function __construct(private readonly IClassMap $classmap) {}

	// Implementation of IBase

	public static function getName(): string {
		return 'agentnodes';
	}

	// Implementation of IOutput

	public function getOutput(string $out = 'html', bool $final = false): string {
		if ($out != 'json') return '';

		$agentNodes = $this->classmap->getInstancesByInterface(IAgentNode::class);

		$result = [];
		foreach ($agentNodes as $agentNode) {
			$result[] = [
				'name' => $agentNode::getName(),
				'class' => get_class($agentNode),
				'description' => $agentNode->getDescription(),
				'inputs' => $this->normalizePortList($agentNode->getInputDefinitions()),
				'outputs' => $this->normalizePortList($agentNode->getOutputDefinitions())
			];
		}

		return json_encode($result, JSON_PRETTY_PRINT);
	}

	private function normalizePortList(array $defs): array {
		$ports = [];

		foreach ($defs as $def) {
			if ($def instanceof AgentNodePort) {
				$ports[] = $def->toArray();
			} elseif (is_string($def)) {
				// fallback für alte string-basierte Definition
				$ports[] = [
					'name' => $def,
					'type' => 'mixed',
					'description' => '',
					'default' => null,
					'required' => false
				];
			}
		}

		return $ports;
	}

	public function getHelp(): string {
		return 'Help of AgentNodes' . "\n";
	}
}
