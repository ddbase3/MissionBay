<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentMemory;
use MissionBay\Agent\AgentContext;

class StringReverserNode implements IAgentNode {

	public function __construct(private readonly string $id) {}

	public static function getName(): string {
		return $this->id; 
	}

	public function getId(): string {
		return 'string_reverser';
	}

	public function getInputDefinitions(): array {
		return ['text'];
	}

	public function getOutputDefinitions(): array {
		return ['reversed'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$text = $inputs['text'] ?? '';
		$reversed = strrev($text);

		return ['reversed' => $reversed];
	}
}

