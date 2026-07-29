<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Orchestrator\Service;

final class AgentToolDefinitionSemantics {

	/** @param array<string,mixed> $definition */
	public function isMutationDefinition(array $definition): bool {
		$annotations = $this->getAnnotations($definition);
		foreach ([
			'destructiveHint', 'destructive', 'requiresApproval', 'requires_confirmation',
			'requiresConfirmation', 'mutation', 'sideEffectHint', 'side_effect'
		] as $key) {
			if (($annotations[$key] ?? false) === true) {
				return true;
			}
		}
		foreach (['readOnlyHint', 'read_only', 'readonly'] as $key) {
			if (array_key_exists($key, $annotations) && $annotations[$key] === false) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $definition */
	public function isExplicitReadOnlyDefinition(array $definition): bool {
		if ($this->isMutationDefinition($definition)) {
			return false;
		}

		$annotations = $this->getAnnotations($definition);
		foreach (['readOnlyHint', 'read_only', 'readonly'] as $key) {
			if (array_key_exists($key, $annotations)) {
				return $annotations[$key] === true;
			}
		}

		return false;
	}

	/** @param array<string,mixed> $definition */
	public function requiresApprovalDefinition(array $definition): bool {
		$annotations = $this->getAnnotations($definition);
		foreach (['destructiveHint', 'destructive'] as $key) {
			if (($annotations[$key] ?? false) === true) {
				return true;
			}
		}
		if (array_key_exists('requiresApproval', $annotations)) {
			return $annotations['requiresApproval'] === true;
		}
		foreach ([
			'requires_confirmation', 'requiresConfirmation', 'mutation',
			'sideEffectHint', 'side_effect'
		] as $key) {
			if (($annotations[$key] ?? false) === true) {
				return true;
			}
		}
		foreach (['readOnlyHint', 'read_only', 'readonly'] as $key) {
			if (array_key_exists($key, $annotations) && $annotations[$key] === false) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $definition */
	public function isBatchableDefinition(array $definition): bool {
		$annotations = $this->getAnnotations($definition);
		return ($annotations['batchable'] ?? $annotations['batch_allowed'] ?? false) === true;
	}

	/** @param array<string,mixed> $definition */
	public function isBatchIndependentDefinition(array $definition): bool {
		$annotations = $this->getAnnotations($definition);
		return ($annotations['batchIndependent'] ?? $annotations['batch_independent'] ?? false) === true;
	}

	/** @param array<string,mixed> $definition */
	public function getMaxBatchSize(array $definition, int $default = 25): int {
		$annotations = $this->getAnnotations($definition);
		$value = $annotations['maxBatchSize'] ?? $annotations['max_batch_size'] ?? $default;
		$value = is_numeric($value) ? (int)$value : $default;
		return max(1, $value);
	}

	/** @param array<string,mixed> $definition */
	public function isCommitGuardRequired(array $definition): bool {
		$annotations = $this->getAnnotations($definition);
		if (array_key_exists('commitGuardRequired', $annotations)) {
			return $annotations['commitGuardRequired'] === true;
		}
		if (array_key_exists('commit_guard_required', $annotations)) {
			return $annotations['commit_guard_required'] === true;
		}
		return true;
	}

	/** @param array<int,array<string,mixed>> $definitions @return array<int,string> */
	public function getMutationToolNames(array $definitions): array {
		$result = [];
		foreach ($definitions as $definition) {
			if (!is_array($definition) || !$this->isMutationDefinition($definition)) {
				continue;
			}
			$name = $this->getToolName($definition);
			if ($name !== '') {
				$result[$name] = $name;
			}
		}
		return array_values($result);
	}

	/** @param array<int,array<string,mixed>> $definitions @return ?array<string,mixed> */
	public function findDefinition(array $definitions, string $toolName): ?array {
		foreach ($definitions as $definition) {
			if (is_array($definition) && $this->getToolName($definition) === $toolName) {
				return $definition;
			}
		}
		return null;
	}

	/** @param array<string,mixed> $definition */
	public function getToolName(array $definition): string {
		$function = is_array($definition['function'] ?? null) ? $definition['function'] : $definition;
		return trim((string)($function['name'] ?? ''));
	}

	/** @param array<string,mixed> $definition @return array<string,mixed> */
	public function getAnnotations(array $definition): array {
		$function = is_array($definition['function'] ?? null) ? $definition['function'] : [];
		$annotations = is_array($definition['annotations'] ?? null) ? $definition['annotations'] : [];
		if (is_array($function['annotations'] ?? null)) {
			$annotations = array_merge($annotations, $function['annotations']);
		}
		foreach ([
			'readOnlyHint', 'read_only', 'readonly', 'destructiveHint', 'destructive',
			'requiresApproval', 'requires_confirmation', 'requiresConfirmation', 'mutation',
			'sideEffectHint', 'side_effect', 'commitGuardRequired', 'commit_guard_required',
			'batchable', 'batch_allowed', 'batchIndependent', 'batch_independent',
			'maxBatchSize', 'max_batch_size'
		] as $key) {
			if (array_key_exists($key, $definition)) {
				$annotations[$key] = $definition[$key];
			}
			if (array_key_exists($key, $function)) {
				$annotations[$key] = $function[$key];
			}
		}
		return $annotations;
	}
}
