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

namespace MissionBay\Resource;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Api\ISchemaProvider;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use Base3\Session\Api\ISession;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentInstructionBlock;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;

class UserPrefsAgentResource extends AbstractAgentResource implements IAgentContextContributor, IAgentTool, IAgentMutationGuardedTool, ISchemaProvider {

	private const SYSTEM_TITLE = 'User preferences';

	private ?ILogger $logger = null;

	private int $priority = 20;

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
		return 'userprefsagentresource';
	}

	public function getDescription(): string {
		return 'Stores user/session preferences via tool calls and contributes them to the system context.';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'priority' => [
					'type' => 'integer',
					'description' => 'Context contribution priority used when multiple contributors are attached to an assistant node. Lower values are loaded first.',
					'default' => 20
				]
			],
			'required' => []
		];
	}

	/**
	 * @return AgentNodeDock[]
	 */
	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger for user prefs events.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->priority = (int)($this->resolver->resolveValue($config['priority'] ?? null) ?? 20);
	}

	public function init(array $resources, IAgentContext $context): void {
		if (!empty($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
			$this->logger = $resources['logger'][0];
			$this->log('logger docked into UserPrefsAgentResource');
		}
	}

	// ----------------------------------------------------
	// Context contribution
	// ----------------------------------------------------

	public function contribute(IAgentContext $context): iterable {
		$lines = $this->buildSystemLines();

		if ($lines === []) {
			return [];
		}

		return [new AgentInstructionBlock(
			id: 'user-preferences',
			content: self::SYSTEM_TITLE . ":\n- " . implode("\n- ", $lines),
			source: $this->id(),
			metadata: ['implementation' => static::getName()]
		)];
	}

	public function getPriority(): int {
		return $this->priority;
	}

	// ----------------------------------------------------
	// IAgentTool
	// ----------------------------------------------------

	public function getToolDefinitions(): array {
		$definitions = $this->loadPrefDefinitions(true);
		$catalog = $this->buildToolDefinitionCatalog($definitions);
		$keySchema = [
			'type' => 'string',
			'description' => 'Exact preference key returned by list_allowed_prefs. Never translate or infer a key from a label.'
		];

		if ($catalog['keys'] !== []) {
			$keySchema['enum'] = $catalog['keys'];
		}

		$valueDescription = 'Canonical preference value returned in allowed_values by list_allowed_prefs. Never translate or infer enum values.';
		if ($catalog['summary'] !== '') {
			$valueDescription .= ' Current catalog: ' . $catalog['summary'];
		}

		return [[
			'type' => 'function',
			'label' => 'User Preferences',
			'category' => 'memory',
			'tags' => ['preferences', 'memory', 'user', 'session'],
			'priority' => 100,
			'readOnlyHint' => true,
			'mutation' => false,
			'requiresApproval' => false,
			'function' => [
				'name' => 'list_allowed_prefs',
				'description' => 'Required discovery step before changing or removing a preference. Returns exact keys, canonical allowed values, types and scopes. Call this before set_user_pref or unset_user_pref instead of guessing from natural-language labels.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'enabled_only' => [
							'type' => 'boolean',
							'description' => 'If true, only returns enabled preference definitions.'
						]
					]
				]
			]
		], [
			'type' => 'function',
			'label' => 'Set User Preference',
			'category' => 'memory',
			'tags' => ['preferences', 'memory', 'user', 'session'],
			'priority' => 60,
			'readOnlyHint' => false,
			'mutation' => true,
			'requiresApproval' => true,
			'commitGuardRequired' => true,
			'sideEffectHint' => true,
			'function' => [
				'name' => 'set_user_pref',
				'description' => 'Sets one preference after list_allowed_prefs has been called. Use only an exact returned key and the canonical returned value. Do not translate labels such as Anrede into keys and do not invent values.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'key' => $keySchema,
						'value' => [
							'description' => $valueDescription
						],
						'scope' => [
							'type' => 'string',
							'enum' => ['user', 'session'],
							'description' => 'Optional scope: "user" or "session". The resource resolves the final supported scope.'
						]
					],
					'required' => ['key', 'value']
				]
			]
		], [
			'type' => 'function',
			'label' => 'Unset User Preference',
			'category' => 'memory',
			'tags' => ['preferences', 'memory', 'user', 'session'],
			'priority' => 60,
			'readOnlyHint' => false,
			'mutation' => true,
			'requiresApproval' => true,
			'commitGuardRequired' => true,
			'sideEffectHint' => true,
			'function' => [
				'name' => 'unset_user_pref',
				'description' => 'Removes one preference after list_allowed_prefs has been called. Use only an exact returned key and never infer a key from a natural-language label.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'key' => $keySchema,
						'scope' => [
							'type' => 'string',
							'enum' => ['user', 'session'],
							'description' => 'Optional scope: "user" or "session". If omitted, removes both for the current identity.'
						]
					],
					'required' => ['key']
				]
			]
		], [
			'type' => 'function',
			'label' => 'List Current Preferences',
			'category' => 'memory',
			'tags' => ['preferences', 'memory', 'user', 'session'],
			'priority' => 55,
			'readOnlyHint' => true,
			'mutation' => false,
			'requiresApproval' => false,
			'function' => [
				'name' => 'list_user_prefs',
				'description' => 'Lists current preference values together with their exact definitions and the complete enabled preference catalog. Use the returned keys and allowed_values for later changes.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'scope' => [
							'type' => 'string',
							'enum' => ['user', 'session'],
							'description' => 'Optional scope filter: "user" or "session". If omitted, returns both.'
						]
					]
				]
			]
		]];
	}

	/**
	 * @param array<int,array<string,mixed>> $definitions
	 * @return array{keys:array<int,string>,summary:string}
	 */
	private function buildToolDefinitionCatalog(array $definitions): array {
		$keys = [];
		$entries = [];

		foreach ($definitions as $definition) {
			$key = trim((string)($definition['pref_key'] ?? ''));
			if ($key === '') {
				continue;
			}

			$keys[] = $key;
			$allowedValues = $this->decodeJsonArray($definition['allowed_values'] ?? null);
			if ($allowedValues !== null && $allowedValues !== []) {
				$entries[] = $key . '=[' . implode('|', array_map(static fn(mixed $value): string => (string)$value, $allowedValues)) . ']';
				continue;
			}

			$entries[] = $key . '=<' . trim((string)($definition['value_type'] ?? 'string')) . '>';
		}

		return [
			'keys' => array_values(array_unique($keys)),
			'summary' => implode('; ', $entries)
		];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		return match ($toolName) {
			'list_allowed_prefs' => $this->toolListAllowedPrefs($arguments),
			'set_user_pref' => $this->toolSetUserPref($arguments),
			'unset_user_pref' => $this->toolUnsetUserPref($arguments),
			'list_user_prefs' => $this->toolListUserPrefs($arguments),
			default => throw new \InvalidArgumentException("Unsupported tool: $toolName")
		};
	}


	// ----------------------------------------------------
	// Mutation commit guard
	// ----------------------------------------------------

	public function captureMutationCommitSnapshot(
		AgentAction $action,
		string $actionFingerprint,
		IAgentContext $context
	): AgentMutationCommitSnapshot {
		$mutation = $this->resolveMutation($action);
		$state = $this->loadMutationState($mutation['key'], $mutation['affected_scopes']);

		return new AgentMutationCommitSnapshot(
			$action->getId(),
			$actionFingerprint,
			$this->buildMutationAuthorization($mutation),
			[
				'plan' => $this->hashData($this->mutationPlanState($mutation)),
				'definition' => $this->hashData($mutation['definition_state']),
				'preference' => $this->hashData($state)
			],
			metadata: [
				'operation' => $mutation['operation'],
				'key' => $mutation['key'],
				'scope' => $mutation['scope'],
				'review' => $this->buildMutationReview($mutation, $state)
			]
		);
	}

	public function validateMutationCommit(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentMutationCommitDecision {
		if ($snapshot->getActionId() !== $action->getId()) {
			return AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
				'User preference mutation snapshot belongs to a different action.'
			);
		}

		try {
			$mutation = $this->resolveMutation($action);
		} catch (\Throwable $e) {
			return AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_REJECTED,
				'User preference mutation is no longer valid: ' . $e->getMessage()
			);
		}

		$metadata = $snapshot->getMetadata();
		if (
			trim((string)($metadata['operation'] ?? '')) !== $mutation['operation']
			|| trim((string)($metadata['key'] ?? '')) !== $mutation['key']
			|| trim((string)($metadata['scope'] ?? '')) !== $mutation['scope']
		) {
			return AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
				'User preference mutation snapshot does not match the approved action.'
			);
		}

		$authorization = $snapshot->getAuthorization();
		if (trim((string)($authorization['resource_id'] ?? '')) !== $this->id()) {
			return AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
				'User preference mutation snapshot belongs to a different component preset.'
			);
		}
		if (!$this->matchesMutationAuthorization($authorization, $mutation)) {
			return AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_UNAUTHORIZED,
				'User or session identity changed after the preference mutation was approved.'
			);
		}

		$versions = $snapshot->getResourceVersions();
		foreach (['plan', 'definition', 'preference'] as $versionKey) {
			if (trim((string)($versions[$versionKey] ?? '')) === '') {
				return AgentMutationCommitDecision::deny(
					AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
					'User preference mutation snapshot is missing required state: ' . $versionKey
				);
			}
		}

		if (!hash_equals((string)$versions['definition'], $this->hashData($mutation['definition_state']))) {
			return AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_STALE,
				'Preference definition changed after approval.'
			);
		}
		if (!hash_equals((string)$versions['plan'], $this->hashData($this->mutationPlanState($mutation)))) {
			return AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_STALE,
				'Preference mutation target changed after approval.'
			);
		}

		$currentState = $this->loadMutationState($mutation['key'], $mutation['affected_scopes']);
		if (!hash_equals((string)$versions['preference'], $this->hashData($currentState))) {
			return AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_STALE,
				'Preference value changed after approval.'
			);
		}

		return AgentMutationCommitDecision::allow(
			'User preference identity, definition and current value are unchanged.'
		);
	}

	/** @return array<string,mixed> */
	private function resolveMutation(AgentAction $action): array {
		$operation = $action->getName();
		if (!in_array($operation, ['set_user_pref', 'unset_user_pref'], true)) {
			throw new \InvalidArgumentException('Unsupported guarded user preference tool: ' . $operation);
		}

		$arguments = $action->getInput();
		$key = trim((string)($arguments['key'] ?? ''));
		if ($key === '') {
			throw new \InvalidArgumentException('Missing parameter: key');
		}

		$definition = $this->loadPrefDefinition($key);
		if ($definition === null) {
			throw new \InvalidArgumentException('Preference key not allowed: ' . $key);
		}
		if ($operation === 'set_user_pref' && !(bool)($definition['enabled'] ?? false)) {
			throw new \InvalidArgumentException('Preference key is disabled: ' . $key);
		}

		$value = null;
		if ($operation === 'set_user_pref') {
			if (!array_key_exists('value', $arguments)) {
				throw new \InvalidArgumentException('Missing parameter: value');
			}
			$validated = $this->validateValue($definition, $arguments['value']);
			if (($validated['ok'] ?? false) !== true) {
				throw new \InvalidArgumentException((string)($validated['error'] ?? 'Invalid value'));
			}
			$value = (string)$validated['value'];
		}

		$requestedScope = strtolower(trim((string)($arguments['scope'] ?? '')));
		if ($requestedScope !== '' && !in_array($requestedScope, ['user', 'session'], true)) {
			throw new \InvalidArgumentException('Invalid preference scope: ' . $requestedScope);
		}
		$scope = $operation === 'unset_user_pref' && $requestedScope === ''
			? 'both'
			: $this->resolveFinalScope($requestedScope, (string)($definition['default_scope'] ?? 'user'));
		$identity = $this->currentIdentity();
		$affectedScopes = $this->resolveAffectedScopes($operation, $scope, $identity);

		return [
			'operation' => $operation,
			'key' => $key,
			'value' => $value,
			'scope' => $scope,
			'affected_scopes' => $affectedScopes,
			'identity' => $identity,
			'definition' => $definition,
			'definition_state' => $this->definitionState($definition)
		];
	}

	/** @param array<string,string> $identity @return string[] */
	private function resolveAffectedScopes(string $operation, string $scope, array $identity): array {
		if ($operation === 'set_user_pref') {
			$scopes = [$scope];
			if ($scope === 'user' && $identity['session_id'] !== '') {
				$scopes[] = 'session';
			}
		} elseif ($scope === 'both') {
			$scopes = [];
			if ($identity['user_id'] !== '') {
				$scopes[] = 'user';
			}
			if ($identity['session_id'] !== '') {
				$scopes[] = 'session';
			}
		} else {
			$scopes = [$scope];
		}

		$scopes = array_values(array_unique($scopes));
		sort($scopes, SORT_STRING);
		if ($scopes === []) {
			throw new \RuntimeException('No user or session identity is available for this preference mutation.');
		}
		if (in_array('user', $scopes, true) && $identity['user_id'] === '') {
			throw new \RuntimeException('No user identity is available for this preference mutation.');
		}
		if (in_array('session', $scopes, true) && $identity['session_id'] === '') {
			throw new \RuntimeException('No session identity is available for this preference mutation.');
		}

		return $scopes;
	}

	/** @return array<string,string> */
	private function currentIdentity(): array {
		$this->session->start();
		$userId = $this->accesscontrol->getUserId();

		return [
			'user_id' => $userId !== null ? trim((string)$userId) : '',
			'session_id' => trim((string)$this->session->getId())
		];
	}

	/** @param array<string,mixed> $mutation @return array<string,string> */
	private function buildMutationAuthorization(array $mutation): array {
		$authorization = ['resource_id' => $this->id()];
		if (in_array('user', $mutation['affected_scopes'], true)) {
			$authorization['user_id'] = $mutation['identity']['user_id'];
		}
		if (in_array('session', $mutation['affected_scopes'], true)) {
			$authorization['session_id'] = $mutation['identity']['session_id'];
		}
		return $authorization;
	}

	/** @param array<string,mixed> $authorization @param array<string,mixed> $mutation */
	private function matchesMutationAuthorization(array $authorization, array $mutation): bool {
		$current = $this->buildMutationAuthorization($mutation);
		foreach (['user_id', 'session_id'] as $key) {
			if (array_key_exists($key, $current) && (string)($authorization[$key] ?? '') !== $current[$key]) {
				return false;
			}
			if (!array_key_exists($key, $current) && array_key_exists($key, $authorization)) {
				return false;
			}
		}
		return true;
	}

	/** @param array<string,mixed> $mutation @return array<string,mixed> */
	private function mutationPlanState(array $mutation): array {
		return [
			'operation' => $mutation['operation'],
			'key' => $mutation['key'],
			'value' => $mutation['value'],
			'scope' => $mutation['scope'],
			'affected_scopes' => $mutation['affected_scopes']
		];
	}

	/** @param array<string,mixed> $definition @return array<string,mixed> */
	private function definitionState(array $definition): array {
		return [
			'pref_key' => (string)($definition['pref_key'] ?? ''),
			'description' => (string)($definition['description'] ?? ''),
			'system_template' => (string)($definition['system_template'] ?? ''),
			'value_type' => (string)($definition['value_type'] ?? ''),
			'allowed_values' => $this->decodeJsonArray($definition['allowed_values'] ?? null),
			'default_scope' => (string)($definition['default_scope'] ?? ''),
			'enabled' => (bool)($definition['enabled'] ?? false),
			'updated' => (string)($definition['updated'] ?? '')
		];
	}

	/** @param string[] $affectedScopes @return array<string,mixed> */
	private function loadMutationState(string $key, array $affectedScopes): array {
		$values = [];
		foreach ($affectedScopes as $scope) {
			$values[$scope] = ['exists' => false, 'value' => '', 'updated' => ''];
		}
		foreach ($this->loadCurrentPrefValues('') as $row) {
			$scope = (string)($row['scope'] ?? '');
			if ((string)($row['pref_key'] ?? '') !== $key || !array_key_exists($scope, $values)) {
				continue;
			}
			$values[$scope] = [
				'exists' => true,
				'value' => (string)($row['pref_value'] ?? ''),
				'updated' => (string)($row['updated'] ?? '')
			];
		}
		ksort($values, SORT_STRING);

		return ['key' => $key, 'values' => $values];
	}

	/** @param array<string,mixed> $mutation @param array<string,mixed> $state @return array<string,mixed> */
	private function buildMutationReview(array $mutation, array $state): array {
		$currentValues = [];
		foreach ($state['values'] as $scope => $valueState) {
			$currentValues[$scope] = ($valueState['exists'] ?? false) === true
				? (string)$valueState['value']
				: null;
		}

		return [
			'operation' => $mutation['operation'] === 'set_user_pref'
				? 'Set user preference'
				: 'Remove user preference',
			'preference' => trim((string)($mutation['definition']['description'] ?? '')) !== ''
				? (string)$mutation['definition']['description']
				: $mutation['key'],
			'key' => $mutation['key'],
			'scope' => $mutation['scope'],
			'current_values' => $currentValues,
			'new_value' => $mutation['value']
		];
	}

	/** @param array<mixed> $value */
	private function hashData(array $value): string {
		$json = json_encode($this->canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($json)) {
			throw new \RuntimeException('User preference mutation state could not be serialized.');
		}
		return hash('sha256', $json);
	}

	private function canonicalize(mixed $value): mixed {
		if (!is_array($value)) {
			return $value;
		}
		if (array_is_list($value)) {
			return array_map(fn(mixed $entry): mixed => $this->canonicalize($entry), $value);
		}
		ksort($value, SORT_STRING);
		foreach ($value as $key => $entry) {
			$value[$key] = $this->canonicalize($entry);
		}
		return $value;
	}


	// ----------------------------------------------------
	// Tools
	// ----------------------------------------------------

	private function toolListAllowedPrefs(array $arguments): array {
		$enabledOnly = (bool)($arguments['enabled_only'] ?? true);

		$defs = $this->loadPrefDefinitions($enabledOnly);

		$out = $this->formatPrefDefinitions($defs);

		$this->log('tool list_allowed_prefs => ' . count($out) . ' defs');
		return [
			'count' => count($out),
			'allowed' => $out
		];
	}

	private function toolSetUserPref(array $arguments): array {
		$key = trim((string)($arguments['key'] ?? ''));
		$value = $arguments['value'] ?? null;

		if ($key === '') {
			return ['error' => 'Missing parameter: key'];
		}

		$def = $this->loadPrefDefinition($key);
		if (!$def) {
			return ['error' => 'Preference key not allowed: ' . $key];
		}
		if (!(bool)$def['enabled']) {
			return ['error' => 'Preference key is disabled: ' . $key];
		}

		$validated = $this->validateValue($def, $value);
		if (($validated['ok'] ?? false) !== true) {
			return ['error' => $validated['error'] ?? 'Invalid value'];
		}

		$requestedScope = trim((string)($arguments['scope'] ?? ''));
		$scope = $this->resolveFinalScope($requestedScope, (string)($def['default_scope'] ?? 'user'));

		$ids = $this->resolveScopeIds($scope);

		$this->upsertPrefValue(
			$scope,
			$key,
			(string)$validated['value'],
			$ids['ident'],
			$ids['userid'],
			$ids['session']
		);

		// If we just saved a user-pref, remove any session-pref for the same key so user wins cleanly.
		if ($scope === 'user') {
			$this->cleanupSessionPref($key);
		}

		$this->log("tool set_user_pref key=$key scope=$scope ident={$ids['ident']}");

		return [
			'ok' => true,
			'key' => $key,
			'scope' => $scope,
			'value' => $validated['value']
		];
	}

	private function toolUnsetUserPref(array $arguments): array {
		$key = trim((string)($arguments['key'] ?? ''));
		if ($key === '') {
			return ['error' => 'Missing parameter: key'];
		}

		$def = $this->loadPrefDefinition($key);
		if (!$def) {
			return ['error' => 'Preference key not allowed: ' . $key];
		}

		$scopeRaw = trim((string)($arguments['scope'] ?? ''));
		$scopeReq = strtolower($scopeRaw);

		// If scope is explicitly given, delete only that scope with normal fallback rules.
		if ($scopeReq === 'user' || $scopeReq === 'session') {

			$scope = $this->resolveFinalScope($scopeReq, (string)($def['default_scope'] ?? 'user'));
			$ids = $this->resolveScopeIds($scope);

			$deleted = $this->deletePrefValue($scope, $key, $ids['ident']);

			$this->log("tool unset_user_pref key=$key scope=$scope ident={$ids['ident']} deleted=" . ($deleted ? 'yes' : 'no'));

			return [
				'ok' => true,
				'key' => $key,
				'scope' => $scope,
				'deleted' => $deleted
			];
		}

		// Otherwise delete both scopes for current identity.
		$deletedUser = $this->deleteUserPrefForCurrentUser($key);
		$deletedSession = $this->deleteSessionPrefForCurrentSession($key);
		$deleted = ($deletedUser || $deletedSession);

		$this->log("tool unset_user_pref key=$key scope=both deletedUser=" . ($deletedUser ? 'yes' : 'no') . " deletedSession=" . ($deletedSession ? 'yes' : 'no'));

		return [
			'ok' => true,
			'key' => $key,
			'scope' => 'both',
			'deleted' => $deleted,
			'deleted_user' => $deletedUser,
			'deleted_session' => $deletedSession
		];
	}

	private function toolListUserPrefs(array $arguments): array {
		$scopeFilter = trim((string)($arguments['scope'] ?? ''));
		$rows = $this->loadCurrentPrefValues($scopeFilter);
		$definitions = $this->formatPrefDefinitions($this->loadPrefDefinitions(true));
		$definitionsByKey = [];

		foreach ($definitions as $definition) {
			$definitionsByKey[(string)$definition['key']] = $definition;
		}

		$out = [];
		foreach ($rows as $row) {
			$key = (string)$row['pref_key'];
			$out[] = [
				'key' => $key,
				'value' => $row['pref_value'],
				'scope' => $row['scope'],
				'updated' => $row['updated'],
				'definition' => $definitionsByKey[$key] ?? null
			];
		}

		$this->log('tool list_user_prefs scope=' . ($scopeFilter ?: 'both') . ' => ' . count($out));

		return [
			'count' => count($out),
			'prefs' => $out,
			'allowed' => $definitions
		];
	}

	/**
	 * @param array<int,array<string,mixed>> $definitions
	 * @return array<int,array<string,mixed>>
	 */
	private function formatPrefDefinitions(array $definitions): array {
		$out = [];

		foreach ($definitions as $definition) {
			$out[] = [
				'key' => (string)($definition['pref_key'] ?? ''),
				'description' => (string)($definition['description'] ?? ''),
				'value_type' => (string)($definition['value_type'] ?? 'string'),
				'allowed_values' => $this->decodeJsonArray($definition['allowed_values'] ?? null),
				'default_scope' => (string)($definition['default_scope'] ?? 'user'),
				'enabled' => (bool)($definition['enabled'] ?? false)
			];
		}

		return $out;
	}

	// ----------------------------------------------------
	// System text generation
	// ----------------------------------------------------

	private function buildSystemLines(): array {
		$defs = $this->loadPrefDefinitions(true);
		if (!$defs) {
			return [];
		}

		$values = $this->loadMergedValuesForCurrentIdentity();

		$lines = [];
		foreach ($defs as $def) {
			$key = (string)$def['pref_key'];

			if (!array_key_exists($key, $values)) {
				continue;
			}

			$line = $this->renderSystemTemplate($def, $values[$key]);
			if ($line !== null && trim($line) !== '') {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	private function renderSystemTemplate(array $def, mixed $value): ?string {
		$type = (string)($def['value_type'] ?? 'string');

		if ($type === 'bool') {
			if ($value === '0' || $value === 0 || $value === false || $value === 'false') {
				return null;
			}
			return (string)($def['system_template'] ?? '');
		}

		$template = (string)($def['system_template'] ?? '');
		if ($template === '') {
			return null;
		}

		return str_replace('{{value}}', (string)$value, $template);
	}

	// ----------------------------------------------------
	// Validation + Scope + Identity
	// ----------------------------------------------------

	private function validateValue(array $def, mixed $value): array {
		$type = (string)($def['value_type'] ?? 'string');

		if ($type === 'bool') {
			$bool = $this->toBool($value);
			return ['ok' => true, 'value' => $bool ? '1' : '0'];
		}

		$val = is_scalar($value) ? (string)$value : json_encode($value);
		$val = trim((string)$val);

		if ($val === '') {
			return ['ok' => false, 'error' => 'Value must not be empty'];
		}

		$allowed = $this->decodeJsonArray($def['allowed_values'] ?? null);
		if ($allowed !== null && $allowed !== []) {
			if (in_array($val, $allowed, true)) {
				return ['ok' => true, 'value' => $val];
			}

			$caseInsensitiveMatches = array_values(array_filter(
				$allowed,
				static fn(mixed $allowedValue): bool => is_scalar($allowedValue)
					&& strcasecmp($val, (string)$allowedValue) === 0
			));
			if (count($caseInsensitiveMatches) === 1) {
				return ['ok' => true, 'value' => (string)$caseInsensitiveMatches[0]];
			}

			return [
				'ok' => false,
				'error' => 'Value not allowed for this key. Allowed values: ' . implode(', ', array_map(static fn(mixed $allowedValue): string => (string)$allowedValue, $allowed))
			];
		}

		return ['ok' => true, 'value' => $val];
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) return $value;
		if (is_int($value)) return $value !== 0;

		$s = strtolower(trim((string)$value));
		return in_array($s, ['1', 'true', 'yes', 'on'], true);
	}

	private function resolveFinalScope(string $requestedScope, string $defaultScope): string {
		$requestedScope = strtolower(trim($requestedScope));
		$defaultScope = strtolower(trim($defaultScope));

		$requestedScope = ($requestedScope === 'user' || $requestedScope === 'session') ? $requestedScope : '';
		$defaultScope = ($defaultScope === 'user' || $defaultScope === 'session') ? $defaultScope : 'user';

		$userid = $this->accesscontrol->getUserId();
		$hasUser = ($userid !== null && (string)$userid !== '');

		if ($requestedScope === 'user' && !$hasUser) {
			return 'session';
		}

		if ($requestedScope !== '') {
			return $requestedScope;
		}

		if ($defaultScope === 'user' && !$hasUser) {
			return 'session';
		}

		return $defaultScope;
	}

	private function resolveScopeIds(string $scope): array {
		$this->session->start();
		$sessionKey = (string)$this->session->getId();

		$userid = $this->accesscontrol->getUserId();
		$useridStr = $userid !== null ? (string)$userid : null;

		if ($scope === 'user') {
			$ident = $this->buildIdent('user', $useridStr, $sessionKey);
			return [
				'userid' => $useridStr,
				'session' => $sessionKey,
				'ident' => $ident
			];
		}

		$ident = $this->buildIdent('session', $useridStr, $sessionKey);
		return [
			'userid' => null,
			'session' => $sessionKey,
			'ident' => $ident
		];
	}

	private function buildIdent(string $scope, ?string $userid, string $sessionKey): string {
		if ($scope === 'user') {
			if ($userid === null || $userid === '') {
				return 's:' . $sessionKey;
			}
			return 'u:' . $userid;
		}
		return 's:' . $sessionKey;
	}

	private function deleteUserPrefForCurrentUser(string $key): bool {
		$this->session->start();
		$sessionKey = (string)$this->session->getId();

		$userid = $this->accesscontrol->getUserId();
		$useridStr = $userid !== null ? (string)$userid : null;

		if ($useridStr === null || $useridStr === '') {
			return false;
		}

		$ident = $this->buildIdent('user', $useridStr, $sessionKey);
		return $this->deletePrefValue('user', $key, $ident);
	}

	private function deleteSessionPrefForCurrentSession(string $key): bool {
		$this->session->start();
		$sessionKey = (string)$this->session->getId();

		if ($sessionKey === '') {
			return false;
		}

		$ident = $this->buildIdent('session', null, $sessionKey);
		return $this->deletePrefValue('session', $key, $ident);
	}

	// ----------------------------------------------------
	// Database I/O
	// ----------------------------------------------------

	private function ensureTables(): void {
		$this->database->connect();

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS base3_missionbay_userpref_def (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				pref_key VARCHAR(100) NOT NULL,
				description VARCHAR(255) NULL,
				system_template TEXT NOT NULL,
				value_type VARCHAR(20) NOT NULL DEFAULT 'string',
				allowed_values LONGTEXT NULL,
				default_scope VARCHAR(20) NOT NULL DEFAULT 'user',
				sort_order INT NOT NULL DEFAULT 100,
				enabled TINYINT(1) NOT NULL DEFAULT 1,
				created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				UNIQUE KEY uq_pref_key (pref_key),
				KEY idx_enabled_sort (enabled, sort_order)
			)
		");

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS base3_missionbay_userpref_value (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				scope VARCHAR(20) NOT NULL,
				ident VARCHAR(128) NOT NULL,
				userid VARCHAR(50) NULL,
				session VARCHAR(64) NULL,
				pref_key VARCHAR(100) NOT NULL,
				pref_value TEXT NOT NULL,
				updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				UNIQUE KEY uq_scope_key_ident (scope, pref_key, ident),
				KEY idx_ident (ident),
				KEY idx_key (pref_key),
				KEY idx_user (userid),
				KEY idx_session (session)
			)
		");
	}

	private function loadPrefDefinitions(bool $enabledOnly): array {
		$this->database->connect();

		$where = $enabledOnly ? 'WHERE enabled=1' : '';
		$q = "SELECT pref_key, description, system_template, value_type, allowed_values, default_scope, sort_order, enabled, updated
				  FROM base3_missionbay_userpref_def
				  $where
				  ORDER BY sort_order ASC, pref_key ASC";

		return $this->database->multiQuery($q) ?? [];
	}

	private function loadPrefDefinition(string $key): ?array {
		$this->database->connect();

		$q = "SELECT pref_key, description, system_template, value_type, allowed_values, default_scope, sort_order, enabled, updated
				  FROM base3_missionbay_userpref_def
				  WHERE pref_key='" . $this->database->escape($key) . "'
				  LIMIT 1";

		$row = $this->database->singleQuery($q);
		return $row ?: null;
	}

	private function upsertPrefValue(string $scope, string $key, string $value, string $ident, ?string $userid, ?string $sessionKey): void {
		$this->database->connect();

		$useridSql = $userid !== null ? "'" . $this->database->escape($userid) . "'" : 'NULL';
		$sessionSql = $sessionKey !== null ? "'" . $this->database->escape($sessionKey) . "'" : 'NULL';

		$q = "INSERT INTO base3_missionbay_userpref_value (scope, ident, userid, session, pref_key, pref_value)
				  VALUES (
					'" . $this->database->escape($scope) . "',
					'" . $this->database->escape($ident) . "',
					$useridSql,
					$sessionSql,
					'" . $this->database->escape($key) . "',
					'" . $this->database->escape($value) . "'
				  )
				  ON DUPLICATE KEY UPDATE
					pref_value=VALUES(pref_value),
					userid=VALUES(userid),
					session=VALUES(session),
					updated=CURRENT_TIMESTAMP";

		$this->database->nonQuery($q);
	}

	private function deletePrefValue(string $scope, string $key, string $ident): bool {
		$this->database->connect();

		$q = "DELETE FROM base3_missionbay_userpref_value
				  WHERE scope='" . $this->database->escape($scope) . "'
						AND pref_key='" . $this->database->escape($key) . "'
						AND ident='" . $this->database->escape($ident) . "'";

		$this->database->nonQuery($q);
		return $this->database->affectedRows() > 0;
	}

	private function cleanupSessionPref(string $key): void {
		$this->session->start();
		$sessionKey = (string)$this->session->getId();
		if ($sessionKey === '') {
			return;
		}

		$ident = $this->buildIdent('session', null, $sessionKey);

		$q = "DELETE FROM base3_missionbay_userpref_value
				  WHERE scope='session'
						AND pref_key='" . $this->database->escape($key) . "'
						AND ident='" . $this->database->escape($ident) . "'";

		$this->database->connect();
		$this->database->nonQuery($q);

		$deleted = $this->database->affectedRows();
		if ($deleted > 0) {
			$this->log("cleanup session pref key=$key deleted=$deleted");
		}
	}

	private function loadCurrentPrefValues(string $scopeFilter): array {
		$this->database->connect();

		$this->session->start();
		$sessionKey = (string)$this->session->getId();

		$userid = $this->accesscontrol->getUserId();
		$useridStr = $userid !== null ? (string)$userid : null;

		$idents = [];
		if ($sessionKey !== '') {
			$idents[] = "'" . $this->database->escape($this->buildIdent('session', null, $sessionKey)) . "'";
		}
		if ($useridStr !== null && $useridStr !== '') {
			$idents[] = "'" . $this->database->escape($this->buildIdent('user', $useridStr, $sessionKey)) . "'";
		}

		if (!$idents) {
			return [];
		}

		$conds = [];
		$scopeFilter = strtolower(trim($scopeFilter));
		if ($scopeFilter === 'user' || $scopeFilter === 'session') {
			$conds[] = "scope='" . $this->database->escape($scopeFilter) . "'";
		}

		$conds[] = 'ident IN (' . implode(',', $idents) . ')';

		$q = "SELECT scope, pref_key, pref_value, updated
				  FROM base3_missionbay_userpref_value
				  WHERE " . implode(' AND ', $conds) . "
				  ORDER BY scope ASC, pref_key ASC";

		return $this->database->multiQuery($q) ?? [];
	}

	private function loadMergedValuesForCurrentIdentity(): array {
		$rows = $this->loadCurrentPrefValues('');
		if (!$rows) {
			return [];
		}

		$values = [];

		// Session first
		foreach ($rows as $r) {
			if (($r['scope'] ?? '') === 'session') {
				$values[(string)$r['pref_key']] = (string)$r['pref_value'];
			}
		}

		// User overrides session
		foreach ($rows as $r) {
			if (($r['scope'] ?? '') === 'user') {
				$values[(string)$r['pref_key']] = (string)$r['pref_value'];
			}
		}

		return $values;
	}

	private function decodeJsonArray(mixed $value): ?array {
		if ($value === null || $value === '') {
			return null;
		}
		if (is_array($value)) {
			return $value;
		}
		$decoded = json_decode((string)$value, true);
		return is_array($decoded) ? $decoded : null;
	}

	// ----------------------------------------------------
	// Logging
	// ----------------------------------------------------

	private function log(string $msg): void {
		if ($this->logger) {
			$this->logger->log(static::getName(), '[' . $this->id . '] ' . $msg);
		}
	}
}

/*
# Example prefs

INSERT INTO base3_missionbay_userpref_def (pref_key, description, system_template, value_type, allowed_values, default_scope, sort_order, enabled)
VALUES
	( "address_form", "Anredeform des Nutzers (Du/Sie).", "Sprich den Nutzer konsequent mit „{{value}}“ an.", "enum", "[\"Du\",\"Sie\"]", "user", 10, 1),
	( "answer_style", "Antwortstil (kurz, normal, ausführlich).", "Antworte im Stil: {{value}}.", "enum", "[\"kurz\",\"normal\",\"ausführlich\"]", "user", 20, 1),
	( "technical_depth", "Technischer Detailgrad (einfach, technisch, expert).", "Passe den technischen Detailgrad an: {{value}}.", "enum", "[\"einfach\",\"technisch\",\"expert\"]", "user", 30, 1),
	( "code_indent", "Einrückung in Codeblöcken (tabs/spaces).", "Bei Code: verwende für Einrückungen {{value}}.", "enum", "[\"tabs\",\"spaces\"]", "user", 40, 1),
	( "code_language", "Kommentare in Code (de/en).", "Bei Code: schreibe Kommentare auf {{value}}.", "enum", "[\"Deutsch\",\"Englisch\"]", "user", 50, 1),
	( "format_preference", "Bevorzugtes Antwortformat (Bulletpoints/Fließtext).", "Bevorzuge als Ausgabeformat: {{value}}.", "enum", "[\"Bulletpoints\",\"Fließtext\"]", "user", 60, 1),
	( "no_long_dash", "Vermeide lange Gedankenstriche in Markdown.", "Vermeide in Markdown lange Gedankenstriche.", "bool", NULL, "user", 70, 1),
	( "ask_clarifying_questions", "Rückfragen stellen, wenn etwas unklar ist.", "Stelle Rückfragen, wenn wichtige Informationen fehlen.", "bool", NULL, "user", 80, 1);
*/
