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
use Base3\Api\ISchemaProvider;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use RuntimeException;

/**
 * ConfiguredChatModelAgentResource
 *
 * Loads a configured LLM service and delegates to the matching
 * IAiChatModel adapter.
 */
class ConfiguredChatModelAgentResource extends AbstractConfiguredServiceAgentResource implements IAiChatModel, ISchemaProvider {


	private const LLM_SETTINGS_GROUP = 'service-llm';
	private const SERVICE_TYPE = 'llm';
	private const SERVICE_ALIAS = 'llm';

	private ?IAiChatModel $model = null;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly ConfiguredServiceRuntimeResolver $runtimeResolver,
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


	public function getSchema(): array {
		return $this->buildConfiguredServiceSchema(
			self::LLM_SETTINGS_GROUP,
			self::SERVICE_TYPE,
			'Configured LLM service id from the service-llm settings group.'
		);
	}
	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->model = null;
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
			throw new RuntimeException(static::class . ' requires config key "service".');
		}

		$service = $this->runtimeResolver->resolve(
			self::LLM_SETTINGS_GROUP,
			$serviceId,
			self::SERVICE_TYPE,
			self::SERVICE_ALIAS,
			IAiChatModel::class,
			$this->optionOverrides
		);

		if(!$service instanceof IAiChatModel) {
			throw new RuntimeException('Configured chat model could not be initialized.');
		}

		$this->model = $service;
		$this->resolvedOptions = $service->getOptions();
	}
}
