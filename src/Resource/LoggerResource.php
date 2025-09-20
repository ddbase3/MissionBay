<?php declare(strict_types=1);

namespace MissionBay\Resource;

use Base3\Logger\Api\ILogger;
use Base3\Logger\LoggerBridgeTrait;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Resource\AbstractAgentResource;

/**
 * LoggerResource
 *
 * Wraps an existing ILogger as a dockable resource.
 * Resolves scope from config (if present) and forwards to the underlying logger.
 */
class LoggerResource extends AbstractAgentResource implements ILogger {

	use LoggerBridgeTrait;

	protected ILogger $logger;
	protected IAgentConfigValueResolver $resolver;
	protected array|string|null $scopeConfig = null;

	public function __construct(ILogger $logger, IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->logger = $logger;
		$this->resolver = $resolver;
	}

	// IBase
	public static function getName(): string {
		return 'loggerresource';
	}

	// IAgentResource
	public function setConfig(array $config): void {
		parent::setConfig($config);
		$this->scopeConfig = $config['scope'] ?? null;
	}

	public function getDescription(): string {
		return 'Provides structured logging functionality to nodes and other resources.';
	}

	// ILogger (core): resolve scope, then delegate
	public function logLevel(string $level, string|\Stringable $message, array $context = []): void {
		$resolvedScope = $this->resolver->resolveValue($this->scopeConfig);
		if (is_string($resolvedScope) && $resolvedScope !== '') {
			$context['scope'] = $resolvedScope;
		}
		if (!isset($context['timestamp'])) {
			$context['timestamp'] = time();
		}
		$this->logger->logLevel($level, $message, $context);
	}

	// Optional: proxy read methods to underlying logger
	public function getScopes(): array {
		return method_exists($this->logger, 'getScopes') ? $this->logger->getScopes() : [];
	}

	public function getNumOfScopes() {
		return method_exists($this->logger, 'getNumOfScopes') ? $this->logger->getNumOfScopes() : 0;
	}

	public function getLogs(string $scope, int $num = 50, bool $reverse = true): array {
		return method_exists($this->logger, 'getLogs') ? $this->logger->getLogs($scope, $num, $reverse) : [];
	}
}

