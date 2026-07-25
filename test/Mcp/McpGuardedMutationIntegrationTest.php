<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionReview;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;
use MissionBay\Api\IConfirmableAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Event\MissionBayAgentActionAuditEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;
use MissionBay\Mcp\McpConfirmationService;
use MissionBay\Mcp\McpConfirmationStore;
use MissionBay\Mcp\McpToolCatalog;
use MissionBay\Mcp\McpToolDefinitionMapper;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Resource\ConfiguredAgentToolResource;
use PHPUnit\Framework\TestCase;

final class McpGuardedMutationIntegrationTest extends TestCase {

	public function testMixedAdministrationToolExecutesReadImmediatelyAndGuardsMutation(): void {
		[$tool, $catalog, $service, $context, $events] = $this->createHarness();

		$pendingRead = $service->createPendingIfNeeded(
			'profile-1',
			'get_ilias_webdav_settings',
			[],
			$catalog,
			$context
		);
		$this->assertSame(null, $pendingRead);
		$readResult = $catalog->call('get_ilias_webdav_settings', [], $context);
		$this->assertSame(['enabled' => true, 'version' => 1], $readResult);
		$this->assertSame(1, $tool->getReadCallCount());
		$this->assertSame(0, $tool->getMutationCallCount());

		$pendingMutation = $service->createPendingIfNeeded(
			'profile-1',
			'update_ilias_webdav_settings',
			['enabled' => false],
			$catalog,
			$context
		);
		$this->assertTrue($pendingMutation['requires_confirmation']);
		$this->assertSame([
			'Current status' => 'enabled',
			'Target status' => 'disabled'
		], $pendingMutation['summary']);
		$this->assertSame(0, $tool->getMutationCallCount());

		$result = $service->handleConfirmationTool(
			'profile-1',
			[
				'confirmation_id' => $pendingMutation['confirmation_id'],
				'decision' => 'accept'
			],
			$catalog,
			$context
		);

		$this->assertTrue($result['ok']);
		$this->assertTrue($result['confirmed']);
		$this->assertSame('accepted', $result['status']);
		$this->assertSame(1, $tool->getMutationCallCount());
		$this->assertSame(['enabled' => false], $tool->getLastMutationArguments());

		$types = $this->eventTypes($events->getFiredEvents());
		$this->assertSame([
			MissionBayToolStartedEvent::class,
			MissionBayToolFinishedEvent::class,
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_REQUESTED,
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_GRANTED,
			MissionBayAgentActionAuditEvent::TYPE_COMMIT_ALLOWED,
			MissionBayToolStartedEvent::class,
			MissionBayToolFinishedEvent::class,
			MissionBayAgentActionAuditEvent::TYPE_COMMIT_SUCCEEDED
		], $types);
	}

	public function testStaleAdministrationSnapshotBlocksMutationAfterApproval(): void {
		[$tool, $catalog, $service, $context, $events] = $this->createHarness();
		$pending = $service->createPendingIfNeeded(
			'profile-1',
			'update_ilias_webdav_settings',
			['enabled' => false],
			$catalog,
			$context
		);
		$tool->setVersion(2);

		$result = $service->handleConfirmationTool(
			'profile-1',
			[
				'confirmation_id' => $pending['confirmation_id'],
				'decision' => 'accept'
			],
			$catalog,
			$context
		);

		$this->assertFalse($result['ok']);
		$this->assertTrue($result['confirmed']);
		$this->assertSame('blocked', $result['status']);
		$this->assertSame(AgentMutationCommitDecision::CODE_STALE, $result['error_code']);
		$this->assertSame(0, $tool->getMutationCallCount());
		$this->assertSame([
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_REQUESTED,
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_GRANTED,
			MissionBayAgentActionAuditEvent::TYPE_COMMIT_BLOCKED
		], $this->eventTypes($events->getFiredEvents()));
	}

	public function testLegacyConfirmableToolRemainsCompatible(): void {
		$events = new McpGuardedRecordingEventManager();
		$context = new AgentContext();
		$tool = new LegacyConfirmableMcpToolTestDouble();
		$wrapper = new ConfiguredAgentToolResource(
			new IdentityAgentConfigValueResolver(),
			$events,
			'legacy-confirmable-tool'
		);
		$wrapper->init(['tool' => [$tool]], $context);
		$logger = new McpGuardedNullLogger();
		$catalog = new McpToolCatalog([$wrapper], new McpToolDefinitionMapper(), $logger);
		$semantics = new AgentToolDefinitionSemantics();
		$service = new McpConfirmationService(
			new McpConfirmationStore(new McpGuardedInMemorySettingsStore()),
			$logger,
			new AgentMutationCommitGuardService(new AgentActionFingerprint(), $events, $semantics),
			$semantics,
			$events
		);
		$pending = $service->createPendingIfNeeded(
			'profile-1',
			'legacy_confirmable_action',
			['id' => 7],
			$catalog,
			$context
		);

		$this->assertTrue($pending['requires_confirmation']);
		$result = $service->handleConfirmationTool(
			'profile-1',
			[
				'confirmation_id' => $pending['confirmation_id'],
				'decision' => 'accept'
			],
			$catalog,
			$context
		);

		$this->assertTrue($result['ok']);
		$this->assertSame('accepted', $result['status']);
		$this->assertSame(1, $tool->getCallCount());
	}

	public function testDeclinedGuardedMutationDoesNotExecute(): void {
		[$tool, $catalog, $service, $context] = $this->createHarness();
		$pending = $service->createPendingIfNeeded(
			'profile-1',
			'update_ilias_webdav_settings',
			['enabled' => false],
			$catalog,
			$context
		);

		$result = $service->handleConfirmationTool(
			'profile-1',
			[
				'confirmation_id' => $pending['confirmation_id'],
				'decision' => 'decline'
			],
			$catalog,
			$context
		);

		$this->assertFalse($result['confirmed']);
		$this->assertSame('declined', $result['status']);
		$this->assertSame(0, $tool->getMutationCallCount());
	}

	/**
	 * @return array{GuardedAdministrationToolTestDouble,McpToolCatalog,McpConfirmationService,AgentContext,McpGuardedRecordingEventManager}
	 */
	private function createHarness(): array {
		$tool = new GuardedAdministrationToolTestDouble();
		$events = new McpGuardedRecordingEventManager();
		$context = new AgentContext();
		$context->setVar('mcp', true);
		$context->setVar('mcp_profile_id', 'profile-1');
		$context->setVar('mcp_profile_label', 'Profile 1');
		$wrapper = new ConfiguredAgentToolResource(
			new IdentityAgentConfigValueResolver(),
			$events,
			'ilias-webdav-administration'
		);
		$wrapper->init(['tool' => [$tool]], $context);
		$logger = new McpGuardedNullLogger();
		$catalog = new McpToolCatalog([$wrapper], new McpToolDefinitionMapper(), $logger);
		$semantics = new AgentToolDefinitionSemantics();
		$service = new McpConfirmationService(
			new McpConfirmationStore(new McpGuardedInMemorySettingsStore()),
			$logger,
			new AgentMutationCommitGuardService(new AgentActionFingerprint(), $events, $semantics),
			$semantics,
			$events
		);

		return [$tool, $catalog, $service, $context, $events];
	}

	/**
	 * @param array<int,object|string> $events
	 * @return array<int,string>
	 */
	private function eventTypes(array $events): array {
		return array_map(static function(object|string $event): string {
			if($event instanceof MissionBayAgentActionAuditEvent) {
				return $event->getType();
			}

			return is_object($event) ? get_class($event) : $event;
		}, $events);
	}
}

final class GuardedAdministrationToolTestDouble implements IAgentTool, IAgentMutationGuardedTool {

	private int $version = 1;
	private int $readCallCount = 0;
	private int $mutationCallCount = 0;
	private array $lastMutationArguments = [];

	public static function getName(): string {
		return 'guardedadministrationtooltestdouble';
	}

	public function getToolDefinitions(): array {
		return [
			[
				'type' => 'function',
				'label' => 'Get ILIAS WebDAV settings',
				'readOnlyHint' => true,
				'mutation' => false,
				'requiresApproval' => false,
				'function' => [
					'name' => 'get_ilias_webdav_settings',
					'description' => 'Reads the current WebDAV settings.',
					'parameters' => ['type' => 'object', 'properties' => []]
				]
			],
			[
				'type' => 'function',
				'label' => 'Update ILIAS WebDAV settings',
				'readOnlyHint' => false,
				'mutation' => true,
				'requiresApproval' => true,
				'commitGuardRequired' => true,
				'function' => [
					'name' => 'update_ilias_webdav_settings',
					'description' => 'Updates the WebDAV settings.',
					'parameters' => [
						'type' => 'object',
						'properties' => ['enabled' => ['type' => 'boolean']],
						'required' => ['enabled']
					]
				]
			]
		];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		if($name === 'get_ilias_webdav_settings') {
			$this->readCallCount++;
			return ['enabled' => true, 'version' => $this->version];
		}

		if($name === 'update_ilias_webdav_settings') {
			$this->mutationCallCount++;
			$this->lastMutationArguments = $arguments;
			return ['ok' => true, 'version' => $this->version];
		}

		throw new \InvalidArgumentException('Unsupported tool: ' . $name);
	}

	public function captureMutationCommitSnapshot(
		AgentAction $action,
		string $actionFingerprint,
		IAgentContext $context
	): AgentMutationCommitSnapshot {
		return new AgentMutationCommitSnapshot(
			$action->getId(),
			$actionFingerprint,
			['user_id' => 7],
			['webdav_settings' => $this->version],
			metadata: [
				'current_status' => 'enabled',
				'target_status' => ($action->getInput()['enabled'] ?? true) ? 'enabled' : 'disabled'
			]
		);
	}

	public function getActionReview(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentActionReview {
		return new AgentActionReview(
			'Update ILIAS WebDAV settings',
			'Apply the reviewed WebDAV configuration?',
			[
				'Current status' => (string)($snapshot->getMetadata()['current_status'] ?? ''),
				'Target status' => (string)($snapshot->getMetadata()['target_status'] ?? '')
			]
		);
	}

	public function validateMutationCommit(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentMutationCommitDecision {
		if(($snapshot->getResourceVersions()['webdav_settings'] ?? null) !== $this->version) {
			return AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_STALE,
				'The WebDAV settings changed after approval.'
			);
		}

		return AgentMutationCommitDecision::allow('The reviewed WebDAV settings are still current.');
	}

	public function setVersion(int $version): void {
		$this->version = $version;
	}

	public function getReadCallCount(): int {
		return $this->readCallCount;
	}

	public function getMutationCallCount(): int {
		return $this->mutationCallCount;
	}

	public function getLastMutationArguments(): array {
		return $this->lastMutationArguments;
	}
}

final class LegacyConfirmableMcpToolTestDouble implements IAgentTool, IConfirmableAgentTool {

	private int $callCount = 0;

	public static function getName(): string {
		return 'legacyconfirmablemcptooltestdouble';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Legacy confirmable action',
			'function' => [
				'name' => 'legacy_confirmable_action',
				'description' => 'Executes one legacy confirmed action.',
				'parameters' => ['type' => 'object', 'properties' => []]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->callCount++;
		return ['ok' => true];
	}

	public function getConfirmationRequest(string $name, array $arguments, IAgentContext $context): ?array {
		return [
			'title' => 'Confirm legacy action',
			'message' => 'Execute the legacy action?',
			'summary' => ['Tool' => $name]
		];
	}

	public function getCallCount(): int {
		return $this->callCount;
	}
}

final class IdentityAgentConfigValueResolver implements IAgentConfigValueResolver {

	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		return $config;
	}
}

final class McpGuardedRecordingEventManager implements IEventManager {

	/** @var array<int,object|string> */
	private array $events = [];

	public function on(string $event, callable $listener, int $priority = 0): void {
	}

	public function once(string $event, callable $listener, int $priority = 0): void {
	}

	public function off(string $event, callable $listener): void {
	}

	public function fire(object|string $event, ...$args): array {
		$this->events[] = $event;
		return [];
	}

	/** @return array<int,object|string> */
	public function getFiredEvents(): array {
		return $this->events;
	}
}

final class McpGuardedInMemorySettingsStore implements ISettingsStore {

	/** @var array<string,array<string,array<string,mixed>>> */
	private array $data = [];

	public function get(string $group, string $name, array $default = []): array {
		return $this->data[$group][$name] ?? $default;
	}

	public function set(string $group, string $name, array $settings): void {
		$this->data[$group][$name] = $settings;
	}

	public function has(string $group, string $name): bool {
		return isset($this->data[$group][$name]);
	}

	public function remove(string $group, string $name): void {
		unset($this->data[$group][$name]);
	}

	public function getGroup(string $group): array {
		return $this->data[$group] ?? [];
	}

	public function save(): void {
	}

	public function reload(): void {
	}
}

final class McpGuardedNullLogger implements ILogger {

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
