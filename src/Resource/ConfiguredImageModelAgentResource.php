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

use AssistantFoundation\Api\IImageGenerationModel;
use AssistantFoundation\Dto\AiImageResult;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use RuntimeException;

/**
 * ConfiguredImageModelAgentResource
 *
 * Loads a configured image generation service and delegates to the matching
 * IImageGenerationModel adapter.
 */
class ConfiguredImageModelAgentResource extends AbstractConfiguredServiceAgentResource implements IImageGenerationModel {

	private const IMAGE_SETTINGS_GROUP = 'service-image';
	private const SERVICE_TYPE = 'image';
	private const SERVICE_ALIAS = 'image';

	private ?IImageGenerationModel $model = null;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly ConfiguredServiceRuntimeResolver $runtimeResolver,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'configuredimagemodelagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured image generation service by id and delegates to the matching image model adapter.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->model = null;
	}

	public function generate(string $prompt, array $options = []): array {
		return $this->ensureModel()->generate($prompt, $options);
	}

	public function generateResult(string $prompt, array $options = []): AiImageResult {
		return $this->ensureModel()->generateResult($prompt, $options);
	}

	protected function ensureConfigured(): void {
		$this->ensureModel();
	}

	protected function applyResolvedOptions(): void {
		if($this->model instanceof IImageGenerationModel) {
			$this->model->setOptions($this->resolvedOptions);
		}
	}

	private function ensureModel(): IImageGenerationModel {
		if($this->model instanceof IImageGenerationModel) {
			return $this->model;
		}

		$this->configureModel();

		if(!$this->model instanceof IImageGenerationModel) {
			throw new RuntimeException('Configured image generation model could not be initialized.');
		}

		return $this->model;
	}

	private function configureModel(): void {
		$serviceId = $this->resolveServiceId();

		if($serviceId === '') {
			throw new RuntimeException(static::class . ' requires config key "service".');
		}

		$service = $this->runtimeResolver->resolve(
			self::IMAGE_SETTINGS_GROUP,
			$serviceId,
			self::SERVICE_TYPE,
			self::SERVICE_ALIAS,
			IImageGenerationModel::class,
			$this->optionOverrides
		);

		if(!$service instanceof IImageGenerationModel) {
			throw new RuntimeException('Configured image generation model could not be initialized.');
		}

		$this->model = $service;
		$this->resolvedOptions = $service->getOptions();
	}
}
