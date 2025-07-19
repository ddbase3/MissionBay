<?php declare(strict_types=1);

namespace MissionBay\Resource;

use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Agent\AgentConfigValueResolver;
use MissionBay\Resource\AbstractAgentResource;

/**
 * LoggerResource
 *
 * Wraps an existing ILogger instance as a dockable resource for nodes.
 * Provides structured logging functionality with flexible scope resolving.
 */
class LoggerResource extends AbstractAgentResource implements ILogger {

	protected ILogger $logger;
	protected IAgentConfigValueResolver $resolver;

	protected array|string|null $scopeConfig = null;

	public function __construct(ILogger $logger, IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->logger = $logger;
		$this->resolver = $resolver;
	}

	// Implementation of IBase

	public static function getName(): string {
		return 'loggerresource';
	}

	// Implementation of IAgentResource

	public function setConfig(array $config): void {
		parent::setConfig($config);
		$this->scopeConfig = $config['scope'] ?? null;
	}

	public function getDescription(): string {
		return 'Provides structured logging functionality to nodes and other resources.';
	}

	// Implementation of ILogger

	public function log(string $scope, string $log, ?int $timestamp = null): bool {
		// Let the resolver decide the final scope (could override the given one)
		$resolvedScope = $this->resolver->resolveValue($this->scopeConfig);

		if (is_string($resolvedScope) && $resolvedScope !== '') {
			$scope = $resolvedScope;
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

