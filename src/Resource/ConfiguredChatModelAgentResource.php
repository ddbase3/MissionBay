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

use AssistantFoundation\Api\IAiChatModel;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\ChatModel\MistralChatModel;
use MissionBay\ChatModel\OpenAiChatModel;
use MissionBay\ChatModel\OpenAiCompatibleChatModel;
use RuntimeException;

/**
 * ConfiguredChatModelAgentResource
 *
 * The only dockable chat model resource used by agent flows.
 * Concrete models are resolved through internal IAiChatModel adapters.
 */
class ConfiguredChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

	private const LLM_SETTINGS_GROUP = 'chat-llm';
	private const PROVIDER_SETTINGS_GROUP = 'ai-provider';

	private array|string|null $llmIdConfig = null;

	/**
	 * @var array<string,mixed>
	 */
	private array $resolvedOptions = [];

	/**
	 * @var array<string,mixed>
	 */
	private array $optionOverrides = [];

	private ?IAiChatModel $model = null;

	public function __construct(
		private readonly IAgentConfigValueResolver $resolver,
		private readonly ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'configuredchatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured chat LLM by id and delegates to the matching internal IAiChatModel adapter.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->llmIdConfig = $config['llmid'] ?? ($config['llm_id'] ?? null);
		$this->model = null;
		$this->resolvedOptions = [];

		$this->configureModel();
	}

	public function getOptions(): array {
		$this->ensureModel();

		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		$this->optionOverrides = array_merge($this->optionOverrides, $options);

		if ($this->model instanceof IAiChatModel) {
			$this->model->setOptions($options);
			$this->resolvedOptions = array_merge($this->resolvedOptions, $options);
		}
	}

	public function chat(array $messages): string {
		return $this->ensureModel()->chat($messages);
	}

	public function raw(array $messages, array $tools = []): mixed {
		return $this->ensureModel()->raw($messages, $tools);
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
		$this->ensureModel()->stream($messages, $tools, $onData, $onMeta);
	}

	private function ensureModel(): IAiChatModel {
		if ($this->model instanceof IAiChatModel) {
			return $this->model;
		}

		$this->configureModel();

		if (!$this->model instanceof IAiChatModel) {
			throw new RuntimeException('Configured chat model could not be initialized.');
		}

		return $this->model;
	}

	private function configureModel(): void {
		$llmId = $this->resolveLlmId();

		if ($llmId === '') {
			throw new RuntimeException('ConfiguredChatModelAgentResource requires config key "llmid".');
		}

		$llmConfig = $this->loadLlmConfig($llmId);

		if (!$this->isConfigEnabled($llmConfig, true)) {
			throw new RuntimeException(
				'Chat LLM config is disabled: ' . $llmId . ' ' . $this->formatConfigDebug($llmConfig)
			);
		}

		$providerId = $this->readProviderId($llmConfig);

		if ($providerId === '') {
			throw new RuntimeException(
				'Chat LLM config has no provider: ' . $llmId . ' ' . $this->formatConfigDebug($llmConfig)
			);
		}

		$providerConfig = $this->loadProviderConfig($providerId);

		if (!$this->isConfigEnabled($providerConfig, true)) {
			throw new RuntimeException(
				'AI provider config is disabled: ' . $providerId . ' ' . $this->formatConfigDebug($providerConfig)
			);
		}

		$driver = $this->normalizeKey((string)($providerConfig['driver'] ?? ''));
		$modelName = $this->resolveChatModelName($driver);

		if ($modelName === '') {
			throw new RuntimeException(
				'AI provider config has no usable driver: ' . $providerId . ' ' . $this->formatConfigDebug($providerConfig)
			);
		}

		$model = $this->classMap->getInstanceByInterfaceName(IAiChatModel::class, $modelName);

		if (!$model instanceof IAiChatModel) {
			throw new RuntimeException(
				'Unable to resolve chat model "' . $modelName . '" for driver "' . $driver . '".'
			);
		}

		$options = $this->buildRuntimeOptions($llmId, $llmConfig, $providerId, $providerConfig);
		$options = array_merge($options, $this->optionOverrides);

		$model->setOptions($options);

		$this->model = $model;
		$this->resolvedOptions = $options;
	}

	private function resolveLlmId(): string {
		$value = $this->resolver->resolveValue($this->llmIdConfig);

		if (!is_scalar($value) && $value !== null) {
			return '';
		}

		return $this->normalizeKey((string)$value);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function loadLlmConfig(string $llmId): array {
		$settings = $this->settingsStore->get(self::LLM_SETTINGS_GROUP, $llmId, []);

		if ($settings === []) {
			throw new RuntimeException(
				'Chat LLM config not found: ' . self::LLM_SETTINGS_GROUP . '/' . $llmId
			);
		}

		return $this->normalizeLlmConfig($llmId, $settings);
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function normalizeLlmConfig(string $llmId, array $settings): array {
		$name = $this->normalizeKey($llmId);
		$label = trim((string)($settings['label'] ?? ''));
		$provider = $this->normalizeKey((string)($settings['provider'] ?? ($settings['provider_id'] ?? '')));
		$model = trim((string)($settings['model'] ?? ''));
		$enabledRaw = $settings['enabled'] ?? true;

		if ($label === '') {
			$label = $name;
		}

		return [
			'name' => $name,
			'label' => $label,
			'provider' => $provider,
			'model' => $model,
			'temperature' => $settings['temperature'] ?? null,
			'max_tokens' => $settings['max_tokens'] ?? ($settings['maxtokens'] ?? null),
			'top_p' => $settings['top_p'] ?? null,
			'timeout_seconds' => $settings['timeout_seconds'] ?? null,
			'connect_timeout_seconds' => $settings['connect_timeout_seconds'] ?? null,
			'params' => is_array($settings['params'] ?? null) ? $settings['params'] : [],
			'enabled' => $this->toBool($enabledRaw, true),
			'enabled_raw' => $enabledRaw,
			'_debug_group' => self::LLM_SETTINGS_GROUP,
			'_debug_name' => $llmId,
			'_debug_raw_settings' => $settings
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function loadProviderConfig(string $providerId): array {
		$settings = $this->settingsStore->get(self::PROVIDER_SETTINGS_GROUP, $providerId, []);

		if ($settings === []) {
			throw new RuntimeException(
				'AI provider config not found: ' . self::PROVIDER_SETTINGS_GROUP . '/' . $providerId
			);
		}

		return $this->normalizeProviderConfig($providerId, $settings);
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function normalizeProviderConfig(string $providerId, array $settings): array {
		$name = $this->normalizeKey($providerId);
		$label = trim((string)($settings['label'] ?? ''));
		$driver = $this->normalizeKey((string)($settings['driver'] ?? ''));
		$endpoint = trim((string)($settings['endpoint'] ?? ''));
		$keyType = $this->normalizeKeyType((string)($settings['keytype'] ?? 'env'));
		$keyValue = trim((string)($settings['keyvalue'] ?? ''));
		$enabledRaw = $settings['enabled'] ?? true;

		if ($label === '') {
			$label = $name;
		}

		return [
			'name' => $name,
			'label' => $label,
			'driver' => $driver,
			'endpoint' => $endpoint,
			'keytype' => $keyType,
			'keyvalue' => $keyValue,
			'enabled' => $this->toBool($enabledRaw, true),
			'enabled_raw' => $enabledRaw,
			'_debug_group' => self::PROVIDER_SETTINGS_GROUP,
			'_debug_name' => $providerId,
			'_debug_raw_settings' => $settings
		];
	}

	/**
	 * @param array<string,mixed> $llmConfig
	 */
	private function readProviderId(array $llmConfig): string {
		foreach (['provider', 'provider_id', 'providerid'] as $key) {
			$value = $llmConfig[$key] ?? null;

			if (is_scalar($value) || $value === null) {
				$providerId = $this->normalizeKey((string)$value);

				if ($providerId !== '') {
					return $providerId;
				}
			}
		}

		$rawSettings = $llmConfig['_debug_raw_settings'] ?? null;

		if (is_array($rawSettings)) {
			foreach (['provider', 'provider_id', 'providerid'] as $key) {
				$value = $rawSettings[$key] ?? null;

				if (is_scalar($value) || $value === null) {
					$providerId = $this->normalizeKey((string)$value);

					if ($providerId !== '') {
						return $providerId;
					}
				}
			}
		}

		return '';
	}

	private function resolveChatModelName(string $driver): string {
		$map = [
			'openai' => OpenAiChatModel::getName(),
			'openai-compatible' => OpenAiCompatibleChatModel::getName(),
			'mistral' => MistralChatModel::getName()
		];

		if (isset($map[$driver])) {
			return $map[$driver];
		}

		return $driver;
	}

	/**
	 * @param array<string,mixed> $llmConfig
	 * @param array<string,mixed> $providerConfig
	 * @return array<string,mixed>
	 */
	private function buildRuntimeOptions(string $llmId, array $llmConfig, string $providerId, array $providerConfig): array {
		$model = trim((string)($llmConfig['model'] ?? ''));

		if ($model === '') {
			throw new RuntimeException(
				'Chat LLM config has no model: ' . $llmId . ' ' . $this->formatConfigDebug($llmConfig)
			);
		}

		$endpoint = trim((string)($providerConfig['endpoint'] ?? ''));

		if ($endpoint === '') {
			throw new RuntimeException(
				'AI provider config has no endpoint: ' . $providerId . ' ' . $this->formatConfigDebug($providerConfig)
			);
		}

		$apiKey = $this->resolveProviderApiKey($providerConfig);

		$options = [
			'llm_id' => $llmId,
			'llm_label' => (string)($llmConfig['label'] ?? $llmId),
			'provider_id' => $providerId,
			'provider_label' => (string)($providerConfig['label'] ?? $providerId),
			'provider_driver' => (string)($providerConfig['driver'] ?? ''),
			'model' => $model,
			'apikey' => $apiKey,
			'endpoint' => $endpoint
		];

		$this->addOptionalNumber($options, 'temperature', $llmConfig['temperature'] ?? null, 'float');
		$this->addOptionalNumber($options, 'max_tokens', $llmConfig['max_tokens'] ?? null, 'int');
		$this->addOptionalNumber($options, 'maxtokens', $llmConfig['max_tokens'] ?? null, 'int');
		$this->addOptionalNumber($options, 'top_p', $llmConfig['top_p'] ?? null, 'float');
		$this->addOptionalNumber($options, 'timeout_seconds', $llmConfig['timeout_seconds'] ?? null, 'int');
		$this->addOptionalNumber($options, 'connect_timeout_seconds', $llmConfig['connect_timeout_seconds'] ?? null, 'int');

		if (is_array($llmConfig['params'] ?? null)) {
			$options = $this->mergeExtraOptions($options, $llmConfig['params']);
		}

		return $options;
	}

	/**
	 * @param array<string,mixed> $providerConfig
	 */
	private function resolveProviderApiKey(array $providerConfig): ?string {
		$keyType = $this->normalizeKey((string)($providerConfig['keytype'] ?? 'env'));
		$keyValue = trim((string)($providerConfig['keyvalue'] ?? ''));

		if ($keyValue === '') {
			throw new RuntimeException(
				'AI provider config has no key value: ' . (string)($providerConfig['name'] ?? 'unknown')
			);
		}

		if ($keyType === 'fixed') {
			$value = $this->resolver->resolveValue([
				'mode' => 'fixed',
				'value' => $keyValue
			]);
		}
		else {
			$value = $this->resolver->resolveValue([
				'mode' => 'env',
				'value' => $keyValue
			]);
		}

		if ($value === null || trim((string)$value) === '') {
			throw new RuntimeException(
				'AI provider API key could not be resolved: ' . (string)($providerConfig['name'] ?? 'unknown')
			);
		}

		return (string)$value;
	}

	/**
	 * @param array<string,mixed> $options
	 */
	private function addOptionalNumber(array &$options, string $key, mixed $value, string $type): void {
		if ($value === null || $value === '') {
			return;
		}

		if (!is_numeric($value)) {
			return;
		}

		if ($type === 'int') {
			$options[$key] = (int)$value;
			return;
		}

		$options[$key] = (float)$value;
	}

	/**
	 * @param array<string,mixed> $options
	 * @param array<string,mixed> $extra
	 * @return array<string,mixed>
	 */
	private function mergeExtraOptions(array $options, array $extra): array {
		$protected = [
			'llm_id' => true,
			'llm_label' => true,
			'provider_id' => true,
			'provider_label' => true,
			'provider_driver' => true,
			'model' => true,
			'apikey' => true,
			'endpoint' => true
		];

		foreach ($extra as $key => $value) {
			if (!is_string($key) || isset($protected[$key])) {
				continue;
			}

			$options[$key] = $value;
		}

		return $options;
	}

	/**
	 * @param array<string,mixed> $config
	 */
	private function isConfigEnabled(array $config, bool $default): bool {
		if (!array_key_exists('enabled', $config)) {
			return $default;
		}

		return $this->toBool($config['enabled'], $default);
	}

	/**
	 * @param array<string,mixed> $config
	 */
	private function formatConfigDebug(array $config): string {
		$debug = [
			'group' => $config['_debug_group'] ?? null,
			'name' => $config['_debug_name'] ?? null,
			'config_keys' => array_keys($config),
			'raw_settings' => $config['_debug_raw_settings'] ?? null,
			'normalized' => $this->removeDebugKeys($config)
		];

		$debug = $this->redactSensitiveConfig($debug);
		$json = json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if (!is_string($json)) {
			return '(debug unavailable)';
		}

		return $json;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	private function removeDebugKeys(array $config): array {
		foreach (array_keys($config) as $key) {
			if (str_starts_with((string)$key, '_debug_')) {
				unset($config[$key]);
			}
		}

		return $config;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	private function redactSensitiveConfig(array $config): array {
		$out = [];

		foreach ($config as $key => $value) {
			$keyString = strtolower((string)$key);

			if ($this->isSensitiveKey($keyString)) {
				$out[$key] = '[redacted]';
				continue;
			}

			if (is_array($value)) {
				$out[$key] = $this->redactSensitiveConfig($value);
				continue;
			}

			$out[$key] = $value;
		}

		return $out;
	}

	private function isSensitiveKey(string $key): bool {
		return in_array($key, [
			'apikey',
			'api_key',
			'keyvalue',
			'token',
			'secret',
			'password',
			'authorization'
		], true);
	}

	private function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	private function normalizeKeyType(string $value): string {
		$value = $this->normalizeKey($value);

		if ($value === 'direct') {
			return 'fixed';
		}

		if (in_array($value, ['env', 'fixed'], true)) {
			return $value;
		}

		return 'env';
	}

	private function toBool(mixed $value, bool $default): bool {
		if ($value === null || $value === '') {
			return $default;
		}

		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value !== 0;
		}

		$value = strtolower(trim((string)$value));

		if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
			return true;
		}

		if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
			return false;
		}

		return $default;
	}
}
