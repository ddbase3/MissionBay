<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class HttpGetNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'httpgetnode';
	}

	public function getInputDefinitions(): array {
		return ['url'];
	}

	public function getOutputDefinitions(): array {
		return ['body', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$url = $inputs['url'] ?? null;

		if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
			return ['error' => 'Invalid or missing URL'];
		}

		$response = @file_get_contents($url);

		if ($response === false) {
			return ['error' => "Failed to fetch URL: $url"];
		}

		return ['body' => $response];
	}
}

