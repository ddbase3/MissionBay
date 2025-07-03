<?php declare(strict_types=1);

namespace MissionBay\Node\Message;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use Base3\Logger\Api\ILogger;
use MissionBay\Node\AbstractAgentNode;

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
		return [
			new AgentNodePort(
				name: 'scope',
				description: 'The log scope or channel name (e.g. "debug", "flow", "alert").',
				type: 'string',
				default: 'default',
				required: false
			),
			new AgentNodePort(
				name: 'message',
				description: 'The message to be logged.',
				type: 'string',
				default: '',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'logged',
				description: 'True if logging succeeded.',
				type: 'bool',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if logging failed.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, IAgentContext $context): array {
		$scope = $inputs['scope'] ?? 'default';
		$message = (string)($inputs['message'] ?? '');

		try {
			$ok = $this->logger->log($scope, $message);
			return ['logged' => $ok];
		} catch (\Throwable $e) {
			return ['error' => $this->error($e->getMessage())];
		}
	}

	public function getDescription(): string {
		return 'Writes a log entry using the provided scope and message. Useful for debugging, tracing flow execution, or recording structured events.';
	}
}

