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

use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Dto\AiEmbeddingResult;
use Base3\Api\ISchemaProvider;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use RuntimeException;

/**
 * ConfiguredEmbeddingModelAgentResource
 *
 * Loads a configured embedding service and delegates to the matching
 * IAiEmbeddingModel adapter.
 */
class ConfiguredEmbeddingModelAgentResource extends AbstractConfiguredServiceAgentResource implements IAiEmbeddingModel, ISchemaProvider {

	private const EMBEDDING_SETTINGS_GROUP = 'service-embedding';
	private const SERVICE_TYPE = 'embedding';
	private const SERVICE_ALIAS = 'embedding';

	private ?IAiEmbeddingModel $model = null;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly ConfiguredServiceRuntimeResolver $runtimeResolver,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'configuredembeddingmodelagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured embedding service by id and delegates to the matching IAiEmbeddingModel adapter.';
	}


	public function getSchema(): array {
		return $this->buildConfiguredServiceSchema(
			self::EMBEDDING_SETTINGS_GROUP,
			self::SERVICE_TYPE,
			'Configured embedding service id from the service-embedding settings group.'
		);
	}
	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->model = null;
	}

	public function embed(array $texts): array {
		return $this->ensureModel()->embed($texts);
	}

	public function embedResult(array $texts): AiEmbeddingResult {
		return $this->ensureModel()->embedResult($texts);
	}

	protected function ensureConfigured(): void {
		$this->ensureModel();
	}

	protected function applyResolvedOptions(): void {
		if($this->model instanceof IAiEmbeddingModel) {
			$this->model->setOptions($this->resolvedOptions);
		}
	}

	private function ensureModel(): IAiEmbeddingModel {
		if($this->model instanceof IAiEmbeddingModel) {
			return $this->model;
		}

		$this->configureModel();

		if(!$this->model instanceof IAiEmbeddingModel) {
			throw new RuntimeException('Configured embedding model could not be initialized.');
		}

		return $this->model;
	}

	private function configureModel(): void {
		$serviceId = $this->resolveServiceId();

		if($serviceId === '') {
			throw new RuntimeException(static::class . ' requires config key "service".');
		}

		$service = $this->runtimeResolver->resolve(
			self::EMBEDDING_SETTINGS_GROUP,
			$serviceId,
			self::SERVICE_TYPE,
			self::SERVICE_ALIAS,
			IAiEmbeddingModel::class,
			$this->optionOverrides
		);

		if(!$service instanceof IAiEmbeddingModel) {
			throw new RuntimeException('Configured embedding model could not be initialized.');
		}

		$this->model = $service;
		$this->resolvedOptions = $service->getOptions();
	}
}
