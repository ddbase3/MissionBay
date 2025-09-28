<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodeDock;
use Base3\Database\Api\IDatabase;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Session\Api\ISession;
use Base3\Logger\Api\ILogger;

class DatabaseMemoryAgentResource extends AbstractAgentResource implements IAgentMemory {

	private ?ILogger $logger = null;

	private string $namespace = 'default';
	private int $max = 20;
	private int $priority = 80;
	private bool $trimHistory = false;

	public function __construct(
		private readonly IDatabase $database,
		private readonly IAgentConfigValueResolver $resolver,
		private readonly IAccesscontrol $accesscontrol,
		private readonly ISession $session,
		?string $id = null
	) {
		parent::__construct($id);
		$this->ensureTables();
	}

	public static function getName(): string {
		return 'databasememoryagentresource';
	}

	public function getDescription(): string {
		return 'Provides database-backed node chat history using IDatabase. Supports user-aware sessions and logging.';
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

		$this->namespace   = (string)($this->resolver->resolveValue($config['namespace'] ?? null) ?? 'default');
		$this->max         = (int)($this->resolver->resolveValue($config['max'] ?? null) ?? 20);
		$this->priority    = (int)($this->resolver->resolveValue($config['priority'] ?? null) ?? 80);
		$this->trimHistory = (bool)($this->resolver->resolveValue($config['trim'] ?? null) ?? false);
	}

	// ------- Dock injection -------
	public function init(array $resources, IAgentContext $context): void {
		if (!empty($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
			$this->logger = $resources['logger'][0];
			$this->logger->log('dbmemory', "logger docked into DatabaseMemoryAgentResource (ns={$this->namespace})");
		}
	}

	private function log(string $msg): void {
		if ($this->logger) {
			$this->logger->log('dbmemory', "[ns={$this->namespace}] $msg");
		}
	}

	// ------- IAgentMemory -------
	public function loadNodeHistory(string $nodeId): array {
		$this->database->connect();
		$sid = $this->ensureSession();

		$q = "SELECT messageid, role, content, payload
		      FROM missionbay_memory_message
		      WHERE session_id=" . (int)$sid . "
		        AND namespace='" . $this->database->escape($this->namespace) . "'
		        AND nodeid='" . $this->database->escape($nodeId) . "'
		      ORDER BY id ASC";

		$rows = $this->database->multiQuery($q);

		$out = [];
		foreach ($rows as $r) {
			$base = [
				'id'      => $r['messageid'],
				'role'    => $r['role'],
				'content' => $r['content'],
			];

			$extras = [];
			if (!empty($r['payload'])) {
				$decoded = json_decode($r['payload'], true);
				if (is_array($decoded)) {
					$extras = $decoded;
				}
			}

			$out[] = $extras ? array_merge($base, $extras) : $base;
		}

		$this->log("load history for $nodeId: " . count($out));
		return $out;
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		$this->database->connect();
		$sid = $this->ensureSession();

		$messageid = $this->database->escape($message['id'] ?? uniqid('msg_', true));
		$role      = $this->database->escape($message['role'] ?? '');
		$content   = $this->database->escape($message['content'] ?? '');

		// store all extras into payload
		$extra = $message;
		unset($extra['id'], $extra['role'], $extra['content']);
		$payloadJson = $extra ? json_encode($extra) : null;
		$payload     = $payloadJson !== null
			? "'" . $this->database->escape($payloadJson) . "'"
			: "NULL";

		$q = "INSERT INTO missionbay_memory_message
		        (session_id, namespace, nodeid, messageid, role, content, payload)
		      VALUES
		        ($sid,
		        '" . $this->database->escape($this->namespace) . "',
		        '" . $this->database->escape($nodeId) . "',
		        '$messageid',
		        '$role',
		        '$content',
		        $payload)";
		$this->database->nonQuery($q);

		$this->trimNodeHistory($sid, $nodeId);
		$this->log("append message for $nodeId (messageid=$messageid)");
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		$this->database->connect();
		$sid = $this->ensureSession();

		$q = "SELECT payload FROM missionbay_memory_message
		      WHERE session_id=$sid
		        AND namespace='" . $this->database->escape($this->namespace) . "'
		        AND nodeid='" . $this->database->escape($nodeId) . "'
		        AND messageid='" . $this->database->escape($messageId) . "'
		      LIMIT 1";
		$row = $this->database->singleQuery($q);
		if (!$row) {
			$this->log("setFeedback failed: msgId=$messageId not found in nodeId=$nodeId");
			return false;
		}

		$payloadArr = $row['payload'] ? json_decode($row['payload'], true) : [];
		if (!is_array($payloadArr)) {
			$payloadArr = [];
		}
		$payloadArr['feedback'] = $feedback;

		$newPayload = $this->database->escape(json_encode($payloadArr));

		$q = "UPDATE missionbay_memory_message
		      SET payload='$newPayload'
		      WHERE session_id=$sid
		        AND namespace='" . $this->database->escape($this->namespace) . "'
		        AND nodeid='" . $this->database->escape($nodeId) . "'
		        AND messageid='" . $this->database->escape($messageId) . "'";
		$this->database->nonQuery($q);
		$ok = $this->database->affectedRows() > 0;

		$this->log("setFeedback for nodeId=$nodeId, msgId=$messageId => " . var_export($feedback, true) . " (ok=" . ($ok ? "yes" : "no") . ")");
		return $ok;
	}

	public function resetNodeHistory(string $nodeId): void {
		$this->database->connect();
		$sid = $this->ensureSession();

		$q = "DELETE FROM missionbay_memory_message
		      WHERE session_id=$sid
		        AND namespace='" . $this->database->escape($this->namespace) . "'
		        AND nodeid='" . $this->database->escape($nodeId) . "'";
		$this->database->nonQuery($q);

		$this->log("reset history for $nodeId");
	}

	public function getPriority(): int {
		return $this->priority;
	}

	// ------- internals -------
	private function ensureTables(): void {
		$this->database->connect();

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS missionbay_memory_session (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				session VARCHAR(64) NOT NULL,
				iphash CHAR(64) NULL,
				userid VARCHAR(50) NULL,
				created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				UNIQUE KEY uq_session (session)
			)
		");

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS missionbay_memory_message (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				session_id BIGINT NOT NULL,
				namespace VARCHAR(100) NOT NULL,
				nodeid VARCHAR(100) NOT NULL,
				messageid VARCHAR(100) NULL,
				role VARCHAR(50) NOT NULL,
				content TEXT NOT NULL,
				payload LONGTEXT NULL,
				created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				FOREIGN KEY (session_id) REFERENCES missionbay_memory_session(id) ON DELETE CASCADE,
				KEY idx_session_namespace_node (session_id, namespace, nodeid)
			)
		");
	}

	private function ensureSession(): int {
		$this->session->start();
		$sesskey = $this->session->getId();

		$this->database->connect();
		$userid = $this->accesscontrol->getUserId();
		$userid = $userid !== null ? "'" . $this->database->escape((string)$userid) . "'" : "NULL";
		$iphash = isset($_SERVER['REMOTE_ADDR'])
			? "'" . hash('sha256', $_SERVER['REMOTE_ADDR']) . "'"
			: "NULL";

		$q = "INSERT INTO missionbay_memory_session (session, iphash, userid)
		      VALUES ('" . $this->database->escape($sesskey) . "', $iphash, $userid)
		      ON DUPLICATE KEY UPDATE 
		          userid=IF(userid IS NULL, VALUES(userid), userid),
			  iphash=VALUES(iphash),
		          id=LAST_INSERT_ID(id)";

		$this->database->nonQuery($q);

		return (int)$this->database->insertId();
	}

	private function trimNodeHistory(int $sid, string $nodeId): void {
		if (!$this->trimHistory) return;

		$this->database->connect();

		$q = "SELECT id FROM missionbay_memory_message
		      WHERE session_id=$sid
		        AND namespace='" . $this->database->escape($this->namespace) . "'
		        AND nodeid='" . $this->database->escape($nodeId) . "'
		      ORDER BY id DESC
		      LIMIT 18446744073709551615 OFFSET " . $this->max;

		$rows = $this->database->multiQuery($q);
		if (count($rows)) {
			$ids = array_map(fn($r) => (int)$r['id'], $rows);
			$this->database->nonQuery(
				"DELETE FROM missionbay_memory_message WHERE id IN (" . implode(',', $ids) . ")"
			);
		}
	}
}

