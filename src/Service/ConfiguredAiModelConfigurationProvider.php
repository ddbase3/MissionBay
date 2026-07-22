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

namespace MissionBay\Service;

use AssistantFoundation\Api\IAiModelConfigurationProvider;
use AssistantFoundation\Dto\AiModelConfiguration;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Transport\ChatCompletionEndpointResolver;
use RuntimeException;

/**
 * Exposes configured LLM services through a runtime-neutral contract.
 */
final class ConfiguredAiModelConfigurationProvider implements IAiModelConfigurationProvider {

	private const LLM_SETTINGS_GROUP = 'service-llm';
	private const CONNECTION_SETTINGS_GROUP = 'connection';

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly IAgentConfigValueResolver $configValueResolver
	) {}

	public static function getName(): string {
		return 'configuredaimodelconfigurationprovider';
	}

	public function getOptions(): array {
		$group = $this->settingsStore->getGroup(self::LLM_SETTINGS_GROUP);
		if (!is_array($group)) {
			return [];
		}

		$options = [];
		foreach ($group as $id => $settings) {
			if ((!is_string($id) && !is_int($id)) || !is_array($settings)) {
				continue;
			}

			$config = ServiceConfig::fromSettings((string)$id, $settings);
			if ($config->getServiceType() !== 'llm') {
				continue;
			}

			$options[] = [
				'id' => $config->getId(),
				'label' => $config->getName(),
				'driver' => $config->getDriver(),
				'model' => $config->getModel(),
				'enabled' => $config->isEnabled()
			];
		}

		usort($options, static function(array $left, array $right): int {
			$result = strcasecmp((string)$left['label'], (string)$right['label']);
			return $result !== 0 ? $result : strcasecmp((string)$left['id'], (string)$right['id']);
		});

		return $options;
	}

	public function has(string $id): bool {
		$id = $this->normalizeKey($id);
		if ($id === '') {
			return false;
		}

		foreach ($this->getOptions() as $option) {
			if ((string)($option['id'] ?? '') !== $id) {
				continue;
			}

			return !empty($option['enabled'])
				&& trim((string)($option['model'] ?? '')) !== ''
				&& in_array((string)($option['driver'] ?? ''), [
					'openai-chat',
					'openai-compatible-chat',
					'mistral-chat'
				], true);
		}

		return false;
	}

	public function get(string $id): AiModelConfiguration {
		$id = $this->normalizeKey($id);
		if ($id === '') {
			throw new RuntimeException('Missing configured LLM id.');
		}

		$settings = $this->settingsStore->get(self::LLM_SETTINGS_GROUP, $id, []);
		if (!is_array($settings) || $settings === []) {
			throw new RuntimeException('Configured LLM not found: ' . $id);
		}

		$service = ServiceConfig::fromSettings($id, $settings);
		if (!$service->isEnabled()) {
			throw new RuntimeException('Configured LLM is disabled: ' . $id);
		}
		if ($service->getServiceType() !== 'llm') {
			throw new RuntimeException('Configured service is not an LLM: ' . $id);
		}
		if ($service->getModel() === '') {
			throw new RuntimeException('Configured LLM has no model: ' . $id);
		}
		if (!in_array($service->getDriver(), ['openai-chat', 'openai-compatible-chat', 'mistral-chat'], true)) {
			throw new RuntimeException('Configured LLM driver is not supported: ' . $service->getDriver());
		}

		$connectionId = $service->getConnectionId();
		$connectionSettings = $this->settingsStore->get(self::CONNECTION_SETTINGS_GROUP, $connectionId, []);
		if (!is_array($connectionSettings) || $connectionSettings === []) {
			throw new RuntimeException('Configured LLM connection not found: ' . $connectionId);
		}

		$connection = ConnectionConfig::fromSettings($connectionId, $connectionSettings);
		if (!$connection->isEnabled()) {
			throw new RuntimeException('Configured LLM connection is disabled: ' . $connectionId);
		}
		if ($connection->getBaseUrl() === '') {
			throw new RuntimeException('Configured LLM connection has no base URL: ' . $connectionId);
		}

		$apiKey = '';
		if ($connection->getAuthType() !== 'none') {
			$secretConfig = $connection->getAuthSecretConfig();
			if ($secretConfig === []) {
				throw new RuntimeException('Configured LLM connection has no secret configuration: ' . $connectionId);
			}
			$value = $this->configValueResolver->resolveValue($secretConfig);
			$apiKey = is_scalar($value) || $value === null ? trim((string)$value) : '';
			if ($apiKey === '') {
				throw new RuntimeException('Configured LLM connection secret could not be resolved: ' . $connectionId);
			}
		}

		$serviceOptions = $service->getOptions();
		$endpoint = ChatCompletionEndpointResolver::resolve(
			$connection->getBaseUrl(),
			$this->resolveChatCompletionPath($serviceOptions)
		);

		return new AiModelConfiguration(
			$service->getId(),
			$service->getName(),
			$service->getDriver(),
			$service->getModel(),
			$endpoint,
			$apiKey,
			$this->normalizeProviderOptions($serviceOptions)
		);
	}

	/** @param array<string,mixed> $options @return array<string,mixed> */
	private function normalizeProviderOptions(array $options): array {
		$result = $options;
		if (array_key_exists('maxTokens', $result)) {
			$result['max_tokens'] = $result['maxTokens'];
			unset($result['maxTokens']);
		}
		if (array_key_exists('topP', $result)) {
			$result['top_p'] = $result['topP'];
			unset($result['topP']);
		}
		unset(
			$result['timeoutSeconds'],
			$result['connectTimeoutSeconds'],
			$result['chat_completion_path'],
			$result['path']
		);
		return $result;
	}

	/** @param array<string,mixed> $options */
	private function resolveChatCompletionPath(array $options): string {
		$path = trim((string)($options['chat_completion_path'] ?? ($options['path'] ?? '')));

		return $path !== '' ? $path : '/v1/chat/completions';
	}

	private function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}
