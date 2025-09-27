<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContext;
use Base3\Session\Api\ISession;
use Base3\Logger\Api\ILogger;
use MissionBay\Agent\AgentNodeDock;

class SessionMemoryAgentResource extends AbstractAgentResource implements IAgentMemory {

	private ISession $session;
	private IAgentConfigValueResolver $resolver;
	private ?ILogger $logger = null;

	private string $namespace = 'default';
	private int $max = 20;
	private int $priority = 80;

	public function __construct(ISession $session, IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->session = $session;
		$this->resolver = $resolver;
		$this->ensureStarted();
		$this->ensureInitialized();
	}

	public static function getName(): string {
		return 'sessionmemoryagentresource';
	}

	public function getDescription(): string {
		return 'Provides session-backed memory (chat history & key/value) using ISession. Can log activity if a logger is docked.';
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger for memory events.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->namespace = (string)($this->resolver->resolveValue($config['namespace'] ?? null) ?? 'default');
		$this->max = (int)($this->resolver->resolveValue($config['max'] ?? null) ?? 20);
		$this->priority = (int)($this->resolver->resolveValue($config['priority'] ?? null) ?? 80);

		$this->ensureInitialized();
	}

	// ------- Dock injection -------
	public function init(array $resources, IAgentContext $context): void {
		if (!empty($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
			$this->logger = $resources['logger'][0];
			$this->logger->log('sessionmemory', "logger docked into SessionMemoryAgentResource (ns={$this->namespace})");
		}
	}

	private function log(string $msg): void {
		if ($this->logger) {
			$this->logger->log('sessionmemory', "[ns={$this->namespace}] $msg");
		}
	}

	// ------- IAgentMemory -------
	public function loadNodeHistory(string $nodeId): array {
		$this->ensureInitialized();
		$h = $this->nodes()[$nodeId] ?? [];
		$this->log("load history for $nodeId: " . count($h));
		return $h;
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		$this->ensureInitialized();
		$nodes = $this->nodes();

		// just store the given message object
		$nodes[$nodeId][] = $message;
		$this->trimNodeHistory($nodes[$nodeId]);
		$this->setNodes($nodes);

		$this->log("append message for $nodeId (len=" . count($nodes[$nodeId]) . ")");
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		$this->ensureInitialized();
		$nodes = $this->nodes();
		if (!isset($nodes[$nodeId])) {
			$this->log("setFeedback failed: nodeId=$nodeId not found");
			return false;
		}
		foreach ($nodes[$nodeId] as &$entry) {
			if (($entry['id'] ?? null) === $messageId) {
				$entry['feedback'] = $feedback;
				$this->setNodes($nodes);
				$this->log("setFeedback for nodeId=$nodeId, msgId=$messageId => " . ($feedback === null ? 'null' : $feedback));
				return true;
			}
		}
		$this->log("setFeedback failed: msgId=$messageId not found in nodeId=$nodeId");
		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		$this->ensureInitialized();
		$nodes = $this->nodes();
		unset($nodes[$nodeId]);
		$this->setNodes($nodes);
		$this->log("reset history for $nodeId");
	}

	public function put(string $key, mixed $value): void {
		$this->ensureInitialized();
		$data = $this->data();
		$data[$key] = $value;
		$this->setData($data);
		$this->log("put key=$key");
	}

	public function get(string $key): mixed {
		$this->ensureInitialized();
		$val = $this->data()[$key] ?? null;
		$this->log("get key=$key => " . ($val === null ? 'null' : 'ok'));
		return $val;
	}

	public function forget(string $key): void {
		$this->ensureInitialized();
		$data = $this->data();
		unset($data[$key]);
		$this->setData($data);
		$this->log("forget key=$key");
	}

	public function keys(): array {
		$this->ensureInitialized();
		$keys = array_keys($this->data());
		$this->log("keys count=" . count($keys));
		return $keys;
	}

	public function getPriority(): int {
		return $this->priority;
	}

	// ------- internals -------
	private function ensureStarted(): void {
		if (!$this->session->started()) {
			$this->session->start();
		}
	}

	private function ensureInitialized(): void {
		if (!$this->session->started()) {
			return;
		}
		if (!isset($_SESSION['mb_memory'])) {
			$_SESSION['mb_memory'] = [];
		}
		if (!isset($_SESSION['mb_memory'][$this->namespace])) {
			$_SESSION['mb_memory'][$this->namespace] = [
				'nodes' => [],
				'data' => [],
			];
		}
	}

	private function &bucket(): array {
		if (!isset($_SESSION['mb_memory'][$this->namespace])) {
			$_SESSION['mb_memory'][$this->namespace] = ['nodes' => [], 'data' => []];
		}
		return $_SESSION['mb_memory'][$this->namespace];
	}

	private function nodes(): array {
		return $this->bucket()['nodes'] ?? [];
	}

	private function setNodes(array $nodes): void {
		$bucket = &$this->bucket();
		$bucket['nodes'] = $nodes;
	}

	private function data(): array {
		return $this->bucket()['data'] ?? [];
	}

	private function setData(array $data): void {
		$bucket = &$this->bucket();
		$bucket['data'] = $data;
	}

	private function trimNodeHistory(array &$history): void {
		if (count($history) > $this->max) {
			$history = array_slice($history, -$this->max);
		}
	}
}

