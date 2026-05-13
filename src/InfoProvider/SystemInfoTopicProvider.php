<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\InfoProvider;

use MissionBay\Api\IAgentInfoTopicProvider;
use MissionBay\Dto\AgentInfoRequest;
use MissionBay\Dto\AgentInfoResult;

/**
 * SystemInfoTopicProvider
 *
 * Read-only provider for safe runtime and system diagnostics.
 *
 * The provider intentionally exposes only compact, filtered information and
 * does not execute administrative commands or perform write operations.
 */
class SystemInfoTopicProvider implements IAgentInfoTopicProvider {

	public static function getName(): string {
		return 'systeminfotopicprovider';
	}

	public function getTopic(): string {
		return 'system';
	}

	public function getTopicAliases(): array {
		return [
			'server',
			'runtime',
			'diagnostics',
			'php'
		];
	}

	public function getTitle(): string {
		return 'System Info';
	}

	public function getDescription(): string {
		return 'Provides read-only runtime diagnostics such as PHP version, OS, load, memory, and disk usage.';
	}

	public function getPriority(): int {
		return 50;
	}

	public function supports(string $topic): bool {
		$topic = $this->normalizeToken($topic);

		if ($topic === $this->getTopic()) {
			return true;
		}

		return in_array($topic, $this->getTopicAliases(), true);
	}

	public function handle(AgentInfoRequest $request): AgentInfoResult {
		return match ($request->scope) {
			'find' => $this->handleFind($request),
			'detail' => $this->handleDetail($request),
			'link' => $this->handleLink($request),
			default => $this->handleSummary($request)
		};
	}

	private function handleFind(AgentInfoRequest $request): AgentInfoResult {
		$items = $this->filterSections($request->query);
		$total = count($items);
		$items = array_slice($items, $request->offset, $request->limit);

		return AgentInfoResult::createSuccess(
			topic: $request->topic,
			scope: $request->scope,
			message: $total > 0 ? 'System info sections found.' : 'No system info sections found.',
			items: $items,
			paging: [
				'offset' => $request->offset,
				'limit' => $request->limit,
				'total' => $total,
				'returned' => count($items)
			]
		);
	}

	private function handleSummary(AgentInfoRequest $request): AgentInfoResult {
		return AgentInfoResult::createSuccess(
			topic: $request->topic,
			scope: $request->scope,
			message: 'System runtime summary.',
			detail: [
				'os' => $this->getOs(),
				'php' => $this->getPhpSummary(),
				'uptime' => $this->getUptime(),
				'load' => $this->getLoad(),
				'memory' => $this->getMemory(),
				'disk' => $this->getDisk()
			]
		);
	}

	private function handleDetail(AgentInfoRequest $request): AgentInfoResult {
		$query = $this->normalizeToken($request->query);

		if ($query === '') {
			return $this->handleSummary($request);
		}

		$section = $this->resolveSection($query);
		if ($section === '') {
			return AgentInfoResult::createError(
				topic: $request->topic,
				scope: $request->scope,
				code: 'unknown_section',
				message: 'Unsupported system info section: ' . $request->query,
				suggestions: array_column($this->getSections(), 'id')
			);
		}

		return AgentInfoResult::createSuccess(
			topic: $request->topic,
			scope: $request->scope,
			message: 'System info detail: ' . $section,
			detail: [
				'section' => $section,
				'data' => $this->getSectionDetail($section)
			]
		);
	}

	private function handleLink(AgentInfoRequest $request): AgentInfoResult {
		return AgentInfoResult::createSuccess(
			topic: $request->topic,
			scope: $request->scope,
			message: 'System provider has no direct administration links.',
			links: []
		);
	}

	private function resolveSection(string $query): string {
		foreach ($this->getSections() as $section) {
			if ($this->normalizeToken((string)$section['id']) === $query) {
				return (string)$section['id'];
			}

			$aliases = $section['aliases'] ?? [];
			foreach ($aliases as $alias) {
				if ($this->normalizeToken((string)$alias) === $query) {
					return (string)$section['id'];
				}
			}
		}

		return '';
	}

	private function getSectionDetail(string $section): array {
		return match ($section) {
			'php' => $this->getPhpDetail(),
			'os' => [
				'name' => php_uname('s'),
				'release' => php_uname('r'),
				'machine' => php_uname('m'),
				'summary' => $this->getOs()
			],
			'uptime' => [
				'formatted' => $this->getUptime(),
				'seconds' => $this->getUptimeSeconds()
			],
			'load' => [
				'averages' => $this->getLoad()
			],
			'memory' => $this->getMemory(),
			'disk' => $this->getDisk(),
			'limits' => $this->getPhpLimits(),
			default => []
		};
	}

	private function filterSections(string $query): array {
		$query = $this->normalizeToken($query);

		if ($query === '') {
			return $this->getSections();
		}

		$out = [];
		foreach ($this->getSections() as $section) {
			$haystack = $this->normalizeToken(implode(' ', [
				(string)$section['id'],
				(string)$section['title'],
				(string)$section['subtitle'],
				implode(' ', $section['aliases'] ?? [])
			]));

			if (str_contains($haystack, $query)) {
				$out[] = $section;
			}
		}

		return $out;
	}

	private function getSections(): array {
		return [
			[
				'id' => 'php',
				'title' => 'PHP runtime',
				'subtitle' => 'PHP version, SAPI, loaded extensions, and selected runtime values.',
				'aliases' => [
					'version',
					'extensions',
					'runtime'
				]
			],
			[
				'id' => 'os',
				'title' => 'Operating system',
				'subtitle' => 'Kernel and machine information from php_uname().',
				'aliases' => [
					'linux',
					'kernel',
					'server'
				]
			],
			[
				'id' => 'uptime',
				'title' => 'Uptime',
				'subtitle' => 'Best-effort uptime information from /proc/uptime.',
				'aliases' => [
					'running',
					'boot'
				]
			],
			[
				'id' => 'load',
				'title' => 'Load averages',
				'subtitle' => 'System load averages if sys_getloadavg() is available.',
				'aliases' => [
					'cpu',
					'loadavg'
				]
			],
			[
				'id' => 'memory',
				'title' => 'Memory',
				'subtitle' => 'Best-effort memory information from /proc/meminfo.',
				'aliases' => [
					'ram',
					'mem'
				]
			],
			[
				'id' => 'disk',
				'title' => 'Disk',
				'subtitle' => 'Disk usage for the root filesystem.',
				'aliases' => [
					'storage',
					'filesystem',
					'free'
				]
			],
			[
				'id' => 'limits',
				'title' => 'PHP limits',
				'subtitle' => 'Selected PHP ini limits relevant for diagnostics.',
				'aliases' => [
					'ini',
					'upload',
					'memory_limit'
				]
			]
		];
	}

	private function getOs(): string {
		return php_uname('s') . ' ' . php_uname('r');
	}

	private function getPhpSummary(): array {
		return [
			'version' => PHP_VERSION,
			'sapi' => PHP_SAPI,
			'loaded_extensions' => count(get_loaded_extensions())
		];
	}

	private function getPhpDetail(): array {
		$extensions = get_loaded_extensions();
		sort($extensions);

		return [
			'version' => PHP_VERSION,
			'sapi' => PHP_SAPI,
			'interface' => php_sapi_name(),
			'extensions_count' => count($extensions),
			'extensions' => $extensions,
			'limits' => $this->getPhpLimits()
		];
	}

	private function getPhpLimits(): array {
		return [
			'memory_limit' => ini_get('memory_limit'),
			'max_execution_time' => ini_get('max_execution_time'),
			'post_max_size' => ini_get('post_max_size'),
			'upload_max_filesize' => ini_get('upload_max_filesize'),
			'max_input_vars' => ini_get('max_input_vars')
		];
	}

	private function getUptime(): ?string {
		$seconds = $this->getUptimeSeconds();
		if ($seconds === null || $seconds <= 0) {
			return null;
		}

		$days = intdiv($seconds, 86400);
		$hours = intdiv($seconds % 86400, 3600);
		$minutes = intdiv($seconds % 3600, 60);

		return sprintf('%d days %d hours %d minutes', $days, $hours, $minutes);
	}

	private function getUptimeSeconds(): ?int {
		if (!is_readable('/proc/uptime')) {
			return null;
		}

		$raw = @file_get_contents('/proc/uptime');
		if (!$raw) {
			return null;
		}

		$parts = explode(' ', trim($raw));
		$seconds = (int)($parts[0] ?? 0);

		return $seconds > 0 ? $seconds : null;
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

		preg_match('/MemTotal:\s+(\d+)/', $data, $totalMatch);
		preg_match('/MemAvailable:\s+(\d+)/', $data, $availableMatch);

		$total = isset($totalMatch[1]) ? round((int)$totalMatch[1] / 1024, 2) : null;
		$available = isset($availableMatch[1]) ? round((int)$availableMatch[1] / 1024, 2) : null;

		return [
			'total_mb' => $total,
			'available_mb' => $available,
			'used_mb' => ($total !== null && $available !== null) ? round($total - $available, 2) : null
		];
	}

	private function getDisk(): array {
		$total = @disk_total_space('/');
		$free = @disk_free_space('/');

		if (!$total || !$free) {
			return [];
		}

		return [
			'path' => '/',
			'total_gb' => round($total / (1024 ** 3), 2),
			'used_gb' => round(($total - $free) / (1024 ** 3), 2),
			'free_gb' => round($free / (1024 ** 3), 2)
		];
	}

	private function normalizeToken(string $value): string {
		$value = trim(mb_strtolower($value));
		return preg_replace('/\s+/u', '_', $value) ?? $value;
	}
}
