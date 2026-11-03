<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentCapabilitySourceMetadata;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\AbstractAgentResource;
use MissionBay\Resource\ConfiguredAgentToolResource;
use MissionBay\Service\AgentComponentPresetMaterializer;
use PHPUnit\Framework\TestCase;

final class MaterializerNullLogger implements ILogger {

	public function emergency(string|\Stringable $message, array $context = []): void {
	}

	public function alert(string|\Stringable $message, array $context = []): void {
	}

	public function critical(string|\Stringable $message, array $context = []): void {
	}

	public function error(string|\Stringable $message, array $context = []): void {
	}

	public function warning(string|\Stringable $message, array $context = []): void {
	}

	public function notice(string|\Stringable $message, array $context = []): void {
	}

	public function info(string|\Stringable $message, array $context = []): void {
	}

	public function debug(string|\Stringable $message, array $context = []): void {
	}

	public function logLevel(string $level, string|\Stringable $message, array $context = []): void {
	}

	public function log(string $scope, string $log, ?int $timestamp = null): bool {
		return true;
	}

	public function getScopes(): array {
		return [];
	}

	public function getNumOfScopes(): int {
		return 0;
	}

	public function getLogs(string $scope, int $num = 50, bool $reverse = true): array {
		return [];
	}
}
