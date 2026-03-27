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
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * NoEmbeddingModelAgentResource
 *
 * A no-operation embedding resource that returns empty vectors.
 */
class NoEmbeddingModelAgentResource extends AbstractAgentResource implements IAiEmbeddingModel {

	protected IAgentConfigValueResolver $resolver;

	protected array|string|null $modelConfig = null;
	protected array|string|null $apikeyConfig = null;
	protected array|string|null $endpointConfig = null;

	protected array $resolvedOptions = [];

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'noembeddingmodelagentresource';
	}

	public function getDescription(): string {
		return 'A no-operation embedding resource that returns empty vectors.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		// Keep same config structure for compatibility, even though values are unused
		$this->modelConfig = $config['model'] ?? null;
		$this->apikeyConfig = $config['apikey'] ?? null;
		$this->endpointConfig = $config['endpoint'] ?? null;

		$this->resolvedOptions = [
			'model' => $this->resolver->resolveValue($this->modelConfig),
			'apikey' => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint' => $this->resolver->resolveValue($this->endpointConfig),
		];
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		// Allows overrides just for compatibility, but they have no effect
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);
	}

	/**
	 * Returns an empty embedding for each text input.
	 *
	 * @param array $texts
	 * @return array
	 */
	public function embed(array $texts): array {
		if (empty($texts)) {
			return [];
		}

		// Return one empty vector per text
		return array_map(fn() => [], $texts);
	}
}
