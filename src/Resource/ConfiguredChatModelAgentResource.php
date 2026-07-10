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
use AssistantFoundation\Dto\AiChatResult;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\ChatModel\MistralChatModel;
use MissionBay\ChatModel\OpenAiChatModel;
use MissionBay\ChatModel\OpenAiCompatibleChatModel;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

/**
 * ConfiguredChatModelAgentResource
 *
 * Loads a configured LLM service and delegates to the matching
 * IAiChatModel adapter.
 */
class ConfiguredChatModelAgentResource extends AbstractConfiguredServiceAgentResource implements IAiChatModel {


	private const LLM_SETTINGS_GROUP = 'service-llm';
	private const CONNECTION_SETTINGS_GROUP = 'connection';
	private const SERVICE_TYPE = 'llm';
	private const SERVICE_ALIAS = 'llm';

	private ?IAiChatModel $model = null;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'configuredchatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured LLM service by id and delegates to the matching IAiChatModel adapter.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->setServiceConfigFromResourceConfig($config);
		$this->model = null;
		$this->resolvedOptions = [];

		$this->configureModel();
	}

	public function complete(array $messages, array $tools = []): AiChatResult {
		return $this->ensureModel()->complete($messages, $tools);
	}

	public function chat(array $messages): string {
		return $this->complete($messages)->getContent();
	}

	public function raw(array $messages, array $tools = []): mixed {
		return $this->ensureModel()->raw($messages, $tools);
	}

	public function streamResult(
		array $messages,
		array $tools,
		callable $onData,
		callable $onMeta = null
	): AiChatResult {
		return $this->ensureModel()->streamResult($messages, $tools, $onData, $onMeta);
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
		$this->ensureModel()->stream($messages, $tools, $onData, $onMeta);
	}

	protected function ensureConfigured(): void {
		$this->ensureModel();
	}

	protected function applyResolvedOptions(): void {
		if($this->model instanceof IAiChatModel) {
			$this->model->setOptions($this->resolvedOptions);
		}
	}

	private function ensureModel(): IAiChatModel {
		if($this->model instanceof IAiChatModel) {
			return $this->model;
		}

		$this->configureModel();

		if(!$this->model instanceof IAiChatModel) {
			throw new RuntimeException('Configured chat model could not be initialized.');
		}

		return $this->model;
	}

	private function configureModel(): void {
		$serviceId = $this->resolveServiceId();

		if($serviceId === '') {
			throw new RuntimeException('ConfiguredChatModelAgentResource requires config key "service".');
		}

		$serviceConfig = $this->loadServiceConfig(self::LLM_SETTINGS_GROUP, $serviceId, self::SERVICE_TYPE);
		$connectionConfig = $this->loadConnectionConfig(self::CONNECTION_SETTINGS_GROUP, $serviceConfig->getConnectionId());

		$modelName = $this->resolveChatModelName($serviceConfig->getDriver());

		if($modelName === '') {
			throw new RuntimeException(
				'LLM service config has no usable driver: ' . $serviceId . ' ' . $this->formatConfigDebug($serviceConfig->toSettings())
			);
		}

		$model = $this->classMap->getInstanceByInterfaceName(IAiChatModel::class, $modelName);

		if(!$model instanceof IAiChatModel) {
			throw new RuntimeException(
				'Unable to resolve chat model "' . $modelName . '" for driver "' . $serviceConfig->getDriver() . '".'
			);
		}

		$this->resolvedOptions = $this->buildRuntimeOptions($serviceConfig, $connectionConfig);
		$this->resolvedOptions = array_merge($this->resolvedOptions, $this->optionOverrides);

		$this->model = $model;
		$this->applyResolvedOptions();
	}

	private function resolveChatModelName(string $driver): string {
		$map = [
			'openai-chat' => OpenAiChatModel::getName(),
			'openai-compatible-chat' => OpenAiCompatibleChatModel::getName(),
			'mistral-chat' => MistralChatModel::getName()
		];

		return $map[$driver] ?? '';
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildRuntimeOptions(ServiceConfig $serviceConfig, ConnectionConfig $connectionConfig): array {
		$options = $this->buildBaseRuntimeOptions($serviceConfig, $connectionConfig, self::SERVICE_ALIAS);
		$serviceOptions = $serviceConfig->getOptions();

		$options = $this->mergeServiceOptions($options, $serviceOptions, [
			'llm_id' => true,
			'llm_label' => true,
			'service_type' => true,
			'service_driver' => true,
			'connection_id' => true,
			'connection_label' => true,
			'connection_type' => true,
			'connection_driver' => true,
			'model' => true,
			'endpoint' => true,
			'apikey' => true,
			'max_tokens' => true,
			'top_p' => true,
			'timeout_seconds' => true,
			'connect_timeout_seconds' => true,
			'maxtokens' => true
		]);

		$this->mapOptionalNumber($options, $serviceOptions, 'temperature', 'temperature', 'float');
		$this->mapOptionalNumber($options, $serviceOptions, 'maxTokens', 'max_tokens', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'topP', 'top_p', 'float');
		$this->mapOptionalNumber($options, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');

		return $options;
	}
}
