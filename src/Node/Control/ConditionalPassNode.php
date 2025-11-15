<?php declare(strict_types=1);

namespace MissionBay\Node\Control;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

/**
 * ConditionalPassNode
 *
 * Prüft eine Session-Variable. Wenn der Wert mit "expected" übereinstimmt,
 * geht der Input auf "blocked", sonst auf "passed".
 */
class ConditionalPassNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'conditionalpassnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'input',
				description: 'The payload to be routed.',
				type: 'mixed',
				required: false
			),
			new AgentNodePort(
				name: 'varname',
				description: 'Name of the $_SESSION variable to check.',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'expected',
				description: 'Expected value of the $_SESSION variable.',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'passed',
				description: 'Emits the input payload if session var does not match expected.',
				type: 'mixed',
				required: false
			),
			new AgentNodePort(
				name: 'blocked',
				description: 'Emits the input payload if session var matches expected.',
				type: 'mixed',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error if varname not provided.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {
		$payload  = $inputs['input'] ?? null;
		$varname  = $inputs['varname'] ?? null;
		$expected = $inputs['expected'] ?? null;

		if (!$varname) {
			return ['error' => 'No varname provided'];
		}

		if (session_status() !== PHP_SESSION_ACTIVE) {
			session_start();
		}

		$current = $_SESSION[$varname] ?? null;

		if ($current === $expected) {
			return ['blocked' => $payload];
		}

		return ['passed' => $payload];
	}

	public function getDescription(): string {
		return 'Checks a $_SESSION variable against an expected value. '
		     . 'If it matches, input goes to "blocked", otherwise to "passed".';
	}
}

