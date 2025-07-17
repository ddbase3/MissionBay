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

	protected string $mode = 'inherit'; // 'default', 'fixed', 'inherit'
	protected ?string $configuredScope = null;

	public function __construct(ILogger $logger, ?string $id = null) {
		parent::__construct($id);
		$this->logger = $logger;
	}

	// Implementation of IBase

	public static function getName(): string {
		return 'loggerresource';
	}

	// Implementation of IAgentResource

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$scopeCfg = $config['scope'] ?? null;

		if (is_array($scopeCfg)) {
			$this->mode = $scopeCfg['mode'] ?? 'inherit';
			$this->configuredScope = $scopeCfg['value'] ?? null;
		} else {
			// Backward compatibility fallback?
			$this->mode = 'inherit';
			$this->configuredScope = null;
		}
	}

	public function getDescription(): string {
		return 'Provides structured logging functionality to nodes and other resources.';
	}

	// Implementation of ILogger 

	public function log(string $scope, string $log, ?int $timestamp = null): bool {
		switch ($this->mode) {
			case 'fixed':
				$scope = $this->configuredScope ?? 'default';
				break;
			case 'default':
				if (empty($scope)) {
					$scope = $this->configuredScope ?? 'default';
				}
				break;
			case 'inherit':
			default:
				// Use given scope
				break;
		}

		return $this->logger->log($scope, $log, $timestamp);
	}

	public function getScopes(): array {
		return [];
	}

	public function getNumOfScopes() {
		return 0;
	}

	public function getLogs(string $scope, int $num = 50, bool $reverse = true): array {
		return [];
	}
}

