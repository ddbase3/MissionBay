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

namespace Base3\MissionBay\Resource;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Api\IAiFileCapableChatModel;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\CoreFoundation\Api\ISchemaProvider;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Service\AiProviderFileReferenceResolver;
use Base3\MissionBay\Service\AiProviderFileReferenceStateRepository;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use Base3\ResourceFoundation\Api\IManagedFileStorageService;
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
	private ?IAgentContext $agentContext = null;
	private bool $fileReferencesPrepared = false;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly ConfiguredServiceRuntimeResolver $runtimeResolver,
		private readonly IStateStore $stateStore,
		private readonly IManagedFileStorageService $managedFileStorage,
		private readonly AiProviderFileReferenceStateRepository $fileReferenceState,
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

	public function init(array $resources, IAgentContext $context): void {
		$this->agentContext = $context;
		$this->fileReferencesPrepared = false;
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->model = null;
		$this->fileReferencesPrepared = false;
	}

	public function complete(array $messages, array $tools = []): AiChatResult {
		return $this->prepareModel()->complete($messages, $tools);
	}

	public function chat(array $messages): string {
		return $this->complete($messages)->getContent();
	}

	public function raw(array $messages, array $tools = []): mixed {
		return $this->prepareModel()->raw($messages, $tools);
	}

	public function streamResult(
		array $messages,
		array $tools,
		callable $onData,
		?callable $onMeta = null
	): AiChatResult {
		return $this->prepareModel()->streamResult($messages, $tools, $onData, $onMeta);
	}

	public function stream(array $messages, array $tools, callable $onData, ?callable $onMeta = null): void {
		$this->prepareModel()->stream($messages, $tools, $onData, $onMeta);
	}

	protected function ensureConfigured(): void {
		$this->ensureModel();
	}

	protected function applyResolvedOptions(): void {
		if($this->model instanceof IAiChatModel) {
			$this->model->setOptions($this->resolvedOptions);
		}
	}

	private function prepareModel(): IAiChatModel {
		$model = $this->ensureModel();
		if($this->fileReferencesPrepared) {
			return $model;
		}

		$this->prepareFileReferences($model);
		$this->fileReferencesPrepared = true;
		return $model;
	}

	private function prepareFileReferences(IAiChatModel $model): void {
		if(!$this->agentContext instanceof IAgentContext) {
			return;
		}

		$agentId = $this->contextString('agent_id');
		if ($agentId !== '') {
			// Explicit agent execution identity takes precedence over any inherited
			// chatbot context. Never resolve another SettingsStore group by ID alone.
			if ($this->contextString('agent_config_group') !== 'agent') {
				if ($model instanceof IAiFileCapableChatModel) {
					$model->setFileReferences([]);
				}
				return;
			}
			$ownerGroup = 'agent';
			$ownerName = $agentId;
		} else {
			$ownerGroup = $this->contextString('chatbot_config_group');
			$ownerName = $this->contextString('chatbot_config_name');
			if ($ownerGroup === '' || $ownerName === '') {
				return;
			}
		}
		$hasFiles = false;
		if($this->managedFileStorage->has(
			$ownerGroup,
			$ownerName,
			IManagedFileStorageService::MODE_COLLECTION
		)) {
			$storage = $this->managedFileStorage->openOrCreate(
				$ownerGroup,
				$ownerName,
				IManagedFileStorageService::MODE_COLLECTION
			);
			foreach($storage->list('') as $item) {
				if(is_array($item) && (string)($item['type'] ?? '') === 'file') {
					$hasFiles = true;
					break;
				}
			}
		}

		if(!$model instanceof IAiFileCapableChatModel) {
			if($hasFiles) {
				throw new RuntimeException('Configured LLM service does not support file resources.');
			}
			return;
		}

		// Only reconcile an empty collection when provider references actually
		// remain. Avoid registering/writing an unused owner on every chatbot turn.
		$serviceId = $this->resolveServiceId();
		if(!$hasFiles && (
			!in_array($serviceId, $this->fileReferenceState->listRegisteredServiceIds($ownerGroup, $ownerName), true)
			|| $this->fileReferenceState->list($serviceId, $ownerGroup, $ownerName) === []
		)) {
			$model->setFileReferences([]);
			return;
		}

		$resolver = new AiProviderFileReferenceResolver(
			$this->managedFileStorage,
			$this->stateStore,
			$this->fileReferenceState
		);
		$model->setFileReferences($resolver->resolve(
			$model,
			$serviceId,
			$ownerGroup,
			$ownerName
		));
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

	private function contextString(string $key): string {
		$value = $this->agentContext?->getVar($key);
		return is_scalar($value) ? trim((string)$value) : '';
	}
}
