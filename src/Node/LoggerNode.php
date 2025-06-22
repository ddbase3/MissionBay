<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Agent\AgentContext;
use Base3\Logger\Api\ILogger;

class LoggerNode extends AbstractAgentNode {

	private ILogger $logger;

	public function __construct(ILogger $logger, ?string $id = null) {
		parent::__construct($id);
		$this->logger = $logger;
	}

	public static function getName(): string {
		return 'loggernode';
	}

	public function getInputDefinitions(): array {
		return ['scope', 'message'];
	}

	public function getOutputDefinitions(): array {
		return ['logged', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$scope = $inputs['scope'] ?? 'default';
		$message = (string)($inputs['message'] ?? '');

		try {
			$ok = $this->logger->log($scope, $message);
			return ['logged' => $ok];
		} catch (\Throwable $e) {
			return ['error' => $e->getMessage()];
		}
	}
}

