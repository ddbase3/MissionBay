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

namespace MissionBay\Cache;

/**
 * Builds deterministic cache keys from tool identity, function, arguments,
 * scope, namespace, and optional rule variant.
 */
final class AgentToolCacheKeyBuilder {

	/**
	 * @param array<string,mixed> $arguments
	 * @return array{key:string,arguments_hash:string,normalized_arguments:array<string,mixed>}
	 */
	public function build(
		string $keyNamespace,
		string $toolIdentity,
		string $toolName,
		array $arguments,
		string $scope,
		string $variant = ''
	): array {
		$normalizedArguments = $this->normalizeArray($arguments);
		$argumentsJson = $this->encode($normalizedArguments);
		$argumentsHash = hash('sha256', $argumentsJson);
		$payload = [
			'version' => 1,
			'namespace' => $keyNamespace,
			'tool_identity' => $toolIdentity,
			'tool' => $toolName,
			'arguments' => $normalizedArguments,
			'scope' => $scope,
			'variant' => $variant
		];

		return [
			'key' => 'agent-tool-result:' . hash('sha256', $this->encode($payload)),
			'arguments_hash' => $argumentsHash,
			'normalized_arguments' => $normalizedArguments
		];
	}

	/**
	 * @param array<mixed> $value
	 * @return array<mixed>
	 */
	private function normalizeArray(array $value): array {
		if (array_is_list($value)) {
			return array_map(fn(mixed $item): mixed => $this->normalizeValue($item), $value);
		}

		ksort($value, SORT_STRING);
		$result = [];

		foreach ($value as $key => $item) {
			$result[(string)$key] = $this->normalizeValue($item);
		}

		return $result;
	}

	private function normalizeValue(mixed $value): mixed {
		if (is_array($value)) {
			return $this->normalizeArray($value);
		}

		if (is_object($value)) {
			if ($value instanceof \JsonSerializable) {
				return $this->normalizeValue($value->jsonSerialize());
			}

			if (method_exists($value, 'toArray')) {
				return $this->normalizeValue($value->toArray());
			}

			throw new \InvalidArgumentException('Tool-cache arguments must be serializable.');
		}

		if (is_resource($value)) {
			throw new \InvalidArgumentException('Tool-cache arguments must not contain resources.');
		}

		return $value;
	}

	private function encode(mixed $value): string {
		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

		if ($json === false) {
			throw new \RuntimeException('Tool-cache key payload could not be encoded.');
		}

		return $json;
	}
}
