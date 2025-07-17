<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Resource\AbstractAgentResource;
use Base3\Logger\Api\ILogger;

/**
 * LoggerResource
 *
 * Wraps an existing ILogger instance as a dockable resource for nodes.
 * Provides structured logging functionality.
 */
class LoggerResource extends AbstractAgentResource implements ILogger {

	protected ILogger $logger;

	public function __construct(ILogger $logger, ?string $id = null) {
		parent::__construct($id);
		$this->logger = $logger;
	}

	// Implementation of IBase

	public static function getName(): string {
		return 'loggerresource';
	}

	public function getDescription(): string {
		return 'Provides structured logging functionality to nodes and other resources.';
	}

	// Implementation of ILogger 

	public function log(string $scope, string $log, ?int $timestamp = null): bool {
		return $this->logger->log($scope, $log, $timestamp);
	}

	public function getScopes(): array {
		return $this->logger->getScopes();
	}

	public function getNumOfScopes() {
		return $this->logger->getNumOfScopes();
	}

	public function getLogs(string $scope, int $num = 50, bool $reverse = true): array {
		return $this->logger->getLogs($scope, $num, $reverse);
	}
}

