<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentContext;

/**
 * SystemStatusAgentTool
 *
 * Provides safe, filtered system information for diagnostics:
 * - OS + PHP version
 * - uptime (best effort)
 * - load averages
 * - memory usage (best effort)
 * - disk usage (root filesystem only)
 *
 * All fields are guarded to avoid fatal errors on systems
 * without /proc or certain functions.
 */
class SystemStatusAgentTool extends AbstractAgentResource implements IAgentTool {

	public static function getName(): string {
		return 'systemstatusagenttool';
	}

	public function getDescription(): string {
		return 'Returns filtered system metrics: uptime, memory, disk, and OS information.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'System Status',
			'category' => 'system',
			'tags' => ['system', 'status', 'health', 'diagnostics'],
			'priority' => 50,
			'function' => [
				'name' => 'system_status',
				'description' => 'Returns safe diagnostic information about the server.',
				'parameters' => [
					'type' => 'object',
					'properties' => (object)[], // OpenAI requires {} for no-params
					'required' => []
				]
			]
		]];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		if ($toolName !== 'system_status') {
			throw new \InvalidArgumentException("Unsupported tool: $toolName");
		}

		return [
			'os'      => $this->getOs(),
			'php'     => $this->getPhp(),
			'uptime'  => $this->getUptime(),
			'load'    => $this->getLoad(),
			'memory'  => $this->getMemory(),
			'disk'    => $this->getDisk()
		];
	}

	// -------------------------------------------------------
	// HELPERS
	// -------------------------------------------------------

	private function getOs(): string {
		return php_uname('s') . ' ' . php_uname('r');
	}

	private function getPhp(): string {
		return PHP_VERSION;
	}

	private function getUptime(): ?string {
		if (!is_readable('/proc/uptime')) {
			return null;
		}

		$raw = @file_get_contents('/proc/uptime');
		if (!$raw) {
			return null;
		}

		$parts = explode(' ', trim($raw));
		$secs = (int)($parts[0] ?? 0);
		if ($secs <= 0) {
			return null;
		}

		$days = intdiv($secs, 86400);
		$hrs  = intdiv($secs % 86400, 3600);
		$min  = intdiv($secs % 3600, 60);

		return sprintf('%d days %d hours %d minutes', $days, $hrs, $min);
	}

	private function getLoad(): array {
		if (!function_exists('sys_getloadavg')) {
			return [];
		}
		return sys_getloadavg() ?: [];
	}

	private function getMemory(): array {
		if (!is_readable('/proc/meminfo')) {
			return [];
		}

		$data = @file_get_contents('/proc/meminfo');
		if (!$data) {
			return [];
		}

		preg_match('/MemTotal:\s+(\d+)/', $data, $m1);
		preg_match('/MemAvailable:\s+(\d+)/', $data, $m2);

		$total = isset($m1[1]) ? (int)$m1[1] / 1024 : null;
		$avail = isset($m2[1]) ? (int)$m2[1] / 1024 : null;

		return [
			'total_mb'     => $total,
			'available_mb' => $avail,
			'used_mb'      => ($total !== null && $avail !== null) ? ($total - $avail) : null
		];
	}

	private function getDisk(): array {
		$total = @disk_total_space('/');
		$free  = @disk_free_space('/');

		if (!$total || !$free) {
			return [];
		}

		return [
			'total_gb' => round($total / (1024 ** 3), 2),
			'used_gb'  => round(($total - $free) / (1024 ** 3), 2),
			'free_gb'  => round($free / (1024 ** 3), 2)
		];
	}
}
