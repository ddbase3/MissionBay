<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use Base3\Logger\Api\ILogger;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentResource;

/**
 * RoutingChatModelAgentResource
 *
 * Routes calls to one of multiple IAiChatModel targets.
 *
 * Features (V1):
 * - strategies: failover, roundrobin (weighted)
 * - per-target capabilities: tools, stream
 * - sticky selection (global or per operation)
 * - light circuit breaker (failures -> cooldown)
 * - optional logging via ILogger
 *
 * Robustness:
 * - Can strip orphaned tool messages before delegating to targets,
 *   preventing OpenAI-compatible backends from rejecting requests.
 */
class RoutingChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

	protected IAgentConfigValueResolver $resolver;

	protected string $strategy = 'failover'; // failover | roundrobin
	protected bool $sticky = true;

	/**
	 * stickyMode:
	 * - global: one sticky selection for all ops (raw + stream)
	 * - per_op: sticky selection per op (raw can differ from stream)
	 */
	protected string $stickyMode = 'global'; // global | per_op

	protected int $maxFailures = 3;
	protected int $cooldownSec = 120;

	/**
	 * target policy config resolved from JSON:
	 * [
	 *   '<targetId>' => ['tools' => bool, 'stream' => bool, 'weight' => int]
	 * ]
	 */
	protected array $targetPolicy = [];

	/** @var IAiChatModel[] */
	protected array $targets = [];

	/** @var array<int, string> index -> targetId */
	protected array $targetIds = [];

	protected ?ILogger $logger = null;
	protected ?IAgentContext $context = null;

	/** @var array<int, int> */
	protected array $failures = [];

	/** @var array<int, int> */
	protected array $cooldownUntil = [];

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'routingchatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Routes chat requests between multiple IAiChatModel resources (failover/roundrobin, capabilities, sticky, cooldown).';
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'targets',
				description: 'Multiple chat model targets implementing IAiChatModel.',
				interface: IAiChatModel::class,
				maxConnections: 99,
				required: true
			),
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		if (isset($config['strategy'])) {
			$this->strategy = (string)($this->resolver->resolveValue($config['strategy']) ?? 'failover');
		}
		if (isset($config['sticky'])) {
			$this->sticky = $this->toBool($this->resolver->resolveValue($config['sticky']), true);
		}
		if (isset($config['stickymode'])) {
			$this->stickyMode = (string)($this->resolver->resolveValue($config['stickymode']) ?? 'global');
		}

		$this->stickyMode = strtolower(trim($this->stickyMode));
		if ($this->stickyMode !== 'per_op') {
			$this->stickyMode = 'global';
		}

		if (isset($config['maxfailures'])) {
			$this->maxFailures = (int)($this->resolver->resolveValue($config['maxfailures']) ?? 3);
		}
		if (isset($config['cooldownsec'])) {
			$this->cooldownSec = (int)($this->resolver->resolveValue($config['cooldownsec']) ?? 120);
		}

		$this->targetPolicy = [];
		$targetsCfg = $config['targets'] ?? null;

		if (is_array($targetsCfg)) {
			foreach ($targetsCfg as $id => $policy) {
				if (!is_string($id) || !is_array($policy)) continue;

				$tools = array_key_exists('tools', $policy)
					? $this->toBool($this->resolveMaybeSpec($policy['tools']), true)
					: true;

				$stream = array_key_exists('stream', $policy)
					? $this->toBool($this->resolveMaybeSpec($policy['stream']), true)
					: true;

				$weight = array_key_exists('weight', $policy)
					? (int)$this->resolveMaybeSpec($policy['weight'])
					: 1;

				if ($weight < 1) $weight = 1;

				$this->targetPolicy[$id] = [
					'tools' => $tools,
					'stream' => $stream,
					'weight' => $weight
				];
			}
		}
	}

	public function init(array $resources, IAgentContext $context): void {
		$this->context = $context;

		$this->targets = [];
		$this->targetIds = [];
		$this->logger = null;

		if (isset($resources['targets'])) {
			foreach ($resources['targets'] as $t) {
				if (!$t instanceof IAiChatModel) continue;

				$this->targets[] = $t;

				if ($t instanceof IAgentResource) {
					$this->targetIds[] = $t->getId();
				} else {
					$this->targetIds[] = 'target_' . count($this->targetIds);
				}
			}
		}

		if (isset($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
			$this->logger = $resources['logger'][0];
		}

		$this->failures = [];
		$this->cooldownUntil = [];
		foreach ($this->targets as $i => $_) {
			$this->failures[$i] = 0;
			$this->cooldownUntil[$i] = 0;
		}

		$this->log("Initialized: targets=" . count($this->targets)
			. " strategy={$this->strategy} sticky=" . ($this->sticky ? 'true' : 'false')
			. " stickyMode={$this->stickyMode}"
			. " maxFailures={$this->maxFailures} cooldownSec={$this->cooldownSec}");
	}

	// ----------------------------------------------------
	// IAiChatModel
	// ----------------------------------------------------

	public function chat(array $messages): string {
		$result = $this->raw($messages, []);
		if (!isset($result['choices'][0]['message']['content'])) {
			throw new \RuntimeException("Malformed chat response (router): " . json_encode($result));
		}
		return (string)$result['choices'][0]['message']['content'];
	}

	public function raw(array $messages, array $tools = []): mixed {
		$needsTools = !empty($tools);

		// If tools are NOT used, strip orphan tool messages defensively.
		$sendMessages = $needsTools ? $messages : $this->stripOrphanedToolMessages($messages);

		return $this->routeCall(
			op: 'raw',
			requiredCap: $needsTools ? 'tools' : null,
			fn: function (IAiChatModel $target) use ($sendMessages, $tools) {
				return $target->raw($sendMessages, $tools);
			}
		);
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
		// Streaming phase should not carry tool messages unless they are properly paired.
		$sendMessages = $this->stripOrphanedToolMessages($messages);

		$this->routeCall(
			op: 'stream',
			requiredCap: 'stream',
			fn: function (IAiChatModel $target) use ($sendMessages, $tools, $onData, $onMeta) {
				$target->stream($sendMessages, $tools, $onData, $onMeta);
				return null;
			}
		);
	}

	public function getOptions(): array {
		$idx = $this->getStickyIndex('raw');
		if ($idx !== null && isset($this->targets[$idx])) {
			$opts = $this->targets[$idx]->getOptions();
			return is_array($opts) ? $opts : [];
		}

		if (isset($this->targets[0])) {
			$opts = $this->targets[0]->getOptions();
			return is_array($opts) ? $opts : [];
		}

		return [];
	}

	public function setOptions(array $options): void {
		foreach ($this->targets as $i => $t) {
			try {
				$t->setOptions($options);
			} catch (\Throwable $e) {
				$this->log("setOptions failed for target #$i (" . ($this->targetIds[$i] ?? 'n/a') . "): " . $e->getMessage());
			}
		}
	}

	// ----------------------------------------------------
	// Routing core
	// ----------------------------------------------------

	protected function routeCall(string $op, ?string $requiredCap, callable $fn): mixed {
		if (count($this->targets) === 0) {
			throw new \RuntimeException('RoutingChatModelAgentResource: no targets connected.');
		}

		$ctx = $this->context;
		$stickyKey = $this->getStickyKey($op);
		$rrKey = 'routingchatmodel.rr.' . $this->getId() . '.' . $op;

		$candidates = $this->buildCandidateIndexes($requiredCap);

		if (count($candidates) === 0) {
			$cap = $requiredCap ? $requiredCap : 'none';
			throw new \RuntimeException("RoutingChatModelAgentResource: no targets match required capability: {$cap}");
		}

		if ($this->sticky && $ctx !== null) {
			$selected = $ctx->getVar($stickyKey);
			if (is_int($selected) && in_array($selected, $candidates, true) && $this->isAvailable($selected)) {
				$this->log("[$op] Sticky reuse target #$selected (" . ($this->targetIds[$selected] ?? 'n/a') . ")");
				try {
					$res = $fn($this->targets[$selected]);
					$this->resetHealth($selected);
					return $res;
				} catch (\Throwable $e) {
					$this->markFailure($selected, $op, $e);
				}
			}
		}

		$attemptOrder = $this->strategy === 'roundrobin'
			? $this->buildRoundRobinOrderWeighted($candidates, $rrKey)
			: $this->buildFailoverOrder($candidates);

		$lastError = null;

		foreach ($attemptOrder as $idx) {
			if (!$this->isAvailable($idx)) {
				continue;
			}

			$this->log("[$op] Attempt target #$idx (" . ($this->targetIds[$idx] ?? 'n/a') . " / " . get_class($this->targets[$idx]) . ")");

			try {
				$res = $fn($this->targets[$idx]);

				$this->resetHealth($idx);

				if ($this->sticky && $ctx !== null) {
					$ctx->setVar($stickyKey, $idx);
				}

				return $res;

			} catch (\Throwable $e) {
				$lastError = $e;
				$this->markFailure($idx, $op, $e);
				continue;
			}
		}

		if ($lastError) {
			throw $lastError;
		}

		throw new \RuntimeException("RoutingChatModelAgentResource: no available targets (all in cooldown or failing).");
	}

	protected function buildCandidateIndexes(?string $requiredCap): array {
		$out = [];
		foreach ($this->targets as $i => $_) {
			if (!$this->isCapable($i, $requiredCap)) {
				continue;
			}
			$out[] = $i;
		}
		return $out;
	}

	protected function isCapable(int $idx, ?string $cap): bool {
		if ($cap === null) return true;

		$id = $this->targetIds[$idx] ?? null;
		$policy = $id ? ($this->targetPolicy[$id] ?? null) : null;

		if (!is_array($policy)) {
			return true;
		}

		if ($cap === 'tools') return (bool)($policy['tools'] ?? true);
		if ($cap === 'stream') return (bool)($policy['stream'] ?? true);

		return true;
	}

	protected function getWeightForIndex(int $idx): int {
		$id = $this->targetIds[$idx] ?? null;
		$policy = $id ? ($this->targetPolicy[$id] ?? null) : null;

		if (!is_array($policy)) return 1;

		$w = (int)($policy['weight'] ?? 1);
		if ($w < 1) $w = 1;
		return $w;
	}

	protected function buildFailoverOrder(array $candidates): array {
		return $candidates;
	}

	protected function buildRoundRobinOrderWeighted(array $candidates, string $rrKey): array {
		$ctx = $this->context;

		if ($ctx === null) return $candidates;

		$weighted = [];
		foreach ($candidates as $idx) {
			$w = $this->getWeightForIndex($idx);
			for ($k = 0; $k < $w; $k++) {
				$weighted[] = $idx;
			}
		}

		if (count($weighted) === 0) return [];

		$pos = $ctx->getVar($rrKey);
		if (!is_int($pos) || $pos < 0) $pos = 0;

		$start = $pos % count($weighted);

		$order = array_merge(
			array_slice($weighted, $start),
			array_slice($weighted, 0, $start)
		);

		$ctx->setVar($rrKey, $pos + 1);

		$seen = [];
		$unique = [];
		foreach ($order as $idx) {
			if (isset($seen[$idx])) continue;
			$seen[$idx] = true;
			$unique[] = $idx;
		}

		foreach ($candidates as $idx) {
			if (!isset($seen[$idx])) {
				$unique[] = $idx;
			}
		}

		return $unique;
	}

	protected function isAvailable(int $idx): bool {
		$until = (int)($this->cooldownUntil[$idx] ?? 0);
		if ($until <= 0) return true;
		return time() >= $until;
	}

	protected function resetHealth(int $idx): void {
		$this->failures[$idx] = 0;
		$this->cooldownUntil[$idx] = 0;
	}

	protected function markFailure(int $idx, string $op, \Throwable $e): void {
		$this->failures[$idx] = (int)($this->failures[$idx] ?? 0) + 1;

		$reason = $this->classifyError($e);
		$this->log("[$op] FAIL target #$idx (" . ($this->targetIds[$idx] ?? 'n/a') . "): {$reason} failures=" . $this->failures[$idx] . " msg=" . $e->getMessage());

		if ($this->failures[$idx] >= $this->maxFailures) {
			$this->cooldownUntil[$idx] = time() + $this->cooldownSec;
			$this->log("[$op] Circuit open for target #$idx until " . date('c', $this->cooldownUntil[$idx]));
		}
	}

	protected function getStickyKey(string $op): string {
		if ($this->stickyMode === 'per_op') {
			return 'routingchatmodel.sticky.' . $this->getId() . '.' . $op;
		}
		return 'routingchatmodel.sticky.' . $this->getId();
	}

	protected function getStickyIndex(string $op): ?int {
		if (!$this->sticky || $this->context === null) {
			return null;
		}
		$stickyKey = $this->getStickyKey($op);
		$selected = $this->context->getVar($stickyKey);
		return is_int($selected) ? $selected : null;
	}

	protected function classifyError(\Throwable $e): string {
		$msg = strtolower($e->getMessage());

		if (str_contains($msg, '429') || str_contains($msg, 'rate limit') || str_contains($msg, 'quota')) {
			return 'rate_limit';
		}
		if (str_contains($msg, 'timeout') || str_contains($msg, 'timed out')) {
			return 'timeout';
		}
		if (str_contains($msg, '500') || str_contains($msg, '502') || str_contains($msg, '503') || str_contains($msg, '504')) {
			return 'server_error';
		}

		return 'error';
	}

	protected function log(string $message): void {
		if (!$this->logger) return;
		$full = '[' . static::getName() . '|' . $this->getId() . '] ' . $message;
		$this->logger->log('RoutingChatModelAgentResource', $full);
	}

	// ----------------------------------------------------
	// Message safety (tool message pairing)
	// ----------------------------------------------------

	protected function stripOrphanedToolMessages(array $messages): array {
		$out = [];
		$validToolCallIds = [];

		foreach ($messages as $m) {
			if (!is_array($m) || !isset($m['role'])) {
				continue;
			}

			$role = (string)$m['role'];

			if ($role === 'assistant' && !empty($m['tool_calls']) && is_array($m['tool_calls'])) {
				foreach ($m['tool_calls'] as $call) {
					if (!isset($call['id'])) continue;
					$validToolCallIds[(string)$call['id']] = true;
				}

				$out[] = $m;
				continue;
			}

			if ($role === 'tool') {
				$toolCallId = (string)($m['tool_call_id'] ?? '');
				if ($toolCallId === '' || empty($validToolCallIds[$toolCallId])) {
					continue;
				}

				$out[] = $m;
				unset($validToolCallIds[$toolCallId]);
				continue;
			}

			$out[] = $m;
		}

		return $out;
	}

	// ----------------------------------------------------
	// Config helpers
	// ----------------------------------------------------

	protected function resolveMaybeSpec(mixed $value): mixed {
		if (is_array($value) || is_string($value) || $value === null) {
			return $this->resolver->resolveValue($value);
		}
		return $value;
	}

	protected function toBool(mixed $value, bool $default): bool {
		if ($value === null) return $default;

		if (is_string($value)) {
			$s = strtolower(trim($value));
			if (in_array($s, ['1', 'true', 'yes', 'on'], true)) return true;
			if (in_array($s, ['0', 'false', 'no', 'off'], true)) return false;
		}

		return (bool)$value;
	}
}
