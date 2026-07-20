<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
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
use MissionBay\Resource\ConfiguredAgentToolResource;
use PHPUnit\Framework\TestCase;

final class McpConfirmationAuditTest extends TestCase {

	public function testAcceptedMcpConfirmationUsesOneCorrelatedAuditIdentity(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(fn(mixed $value): mixed => $value);
		$logger = $this->createStub(ILogger::class);
		$eventManager = new McpAuditRecordingEventManager();
		$context = new AgentContext();
		$context->setVar('mcp', true);
		$context->setVar('mcp_profile_id', 'profile-1');
		$context->setVar('mcp_profile_label', 'Profile 1');

		$wrapper = new ConfiguredAgentToolResource($resolver, $eventManager, 'configured_mcp_tool');
		$wrapper->init(['tool' => [new ConfirmableMcpToolTestDouble()]], $context);
		$catalog = new McpToolCatalog([$wrapper], new McpToolDefinitionMapper(), $logger);
		$service = new McpConfirmationService(
			new McpConfirmationStore(new InMemoryMcpSettingsStore()),
			$logger,
			$eventManager
		);

		$pending = $service->createPendingIfNeeded(
			'profile-1',
			'dangerous_tool',
			['record_id' => 42],
			$catalog,
			$context
		);

		$this->assertIsArray($pending);
		$this->assertTrue($pending['requires_confirmation']);

		$result = $service->handleConfirmationTool(
			'profile-1',
			[
				'confirmation_id' => $pending['confirmation_id'],
				'decision' => 'accept',
				'note' => 'Approved by test.'
			],
			$catalog,
			$context
		);
		$events = $eventManager->getFiredEvents();

		$this->assertTrue($result['confirmed']);
		$this->assertCount(4, $events);
		$this->assertInstanceOf(MissionBayAgentActionAuditEvent::class, $events[0]);
		$this->assertSame(MissionBayAgentActionAuditEvent::TYPE_APPROVAL_REQUESTED, $events[0]->getType());
		$this->assertInstanceOf(MissionBayAgentActionAuditEvent::class, $events[1]);
		$this->assertSame(MissionBayAgentActionAuditEvent::TYPE_APPROVAL_GRANTED, $events[1]->getType());
		$this->assertInstanceOf(MissionBayToolStartedEvent::class, $events[2]);
		$this->assertInstanceOf(MissionBayToolFinishedEvent::class, $events[3]);
		$this->assertSame($events[0]->getAction()->getId(), $events[2]->getCallId());
		$this->assertSame($events[2]->getCallId(), $events[3]->getCallId());
		$this->assertSame('configured_mcp_tool', $events[2]->getNodeId());
		$this->assertSame('mcp', $events[2]->getTrace()['source']);
	}

	public function testDeclinedMcpConfirmationDoesNotExecuteTool(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(fn(mixed $value): mixed => $value);
		$logger = $this->createStub(ILogger::class);
		$eventManager = new McpAuditRecordingEventManager();
		$context = new AgentContext();
		$tool = new ConfirmableMcpToolTestDouble();
		$wrapper = new ConfiguredAgentToolResource($resolver, $eventManager, 'configured_mcp_tool');
		$wrapper->init(['tool' => [$tool]], $context);
		$catalog = new McpToolCatalog([$wrapper], new McpToolDefinitionMapper(), $logger);
		$service = new McpConfirmationService(
			new McpConfirmationStore(new InMemoryMcpSettingsStore()),
			$logger,
			$eventManager
		);
		$pending = $service->createPendingIfNeeded(
			'profile-1',
			'dangerous_tool',
			[],
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
		$events = $eventManager->getFiredEvents();

		$this->assertFalse($result['confirmed']);
		$this->assertSame(0, $tool->getCallCount());
		$this->assertCount(2, $events);
		$this->assertSame(MissionBayAgentActionAuditEvent::TYPE_APPROVAL_DENIED, $events[1]->getType());
	}
}

final class ConfirmableMcpToolTestDouble implements IAgentTool, IConfirmableAgentTool {

	private int $callCount = 0;

	public static function getName(): string {
		return 'confirmablemcptooltestdouble';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Dangerous Tool',
			'function' => [
				'name' => 'dangerous_tool',
				'description' => 'Executes a confirmed action.',
				'parameters' => [
					'type' => 'object',
					'properties' => []
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->callCount++;
		return ['ok' => true, 'arguments' => $arguments];
	}

	public function getConfirmationRequest(string $name, array $arguments, IAgentContext $context): ?array {
		return [
			'title' => 'Confirm action',
			'message' => 'Execute the action?',
			'summary' => ['Tool: ' . $name],
			'risk' => 'high'
		];
	}

	public function getCallCount(): int {
		return $this->callCount;
	}
}

final class McpAuditRecordingEventManager implements IEventManager {

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

final class InMemoryMcpSettingsStore implements ISettingsStore {

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
