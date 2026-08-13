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

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContentParser;
use MissionBay\Api\IConfiguredParserServiceResolver;
use MissionBay\Api\IParserService;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;
use RuntimeException;

/**
 * ConfiguredParserServiceAgentResource
 *
 * Loads one configured parser service and delegates content parsing to it.
 */
final class ConfiguredParserServiceAgentResource extends AbstractConfiguredServiceAgentResource implements IAgentContentParser {

	private ?IParserService $service = null;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly IConfiguredParserServiceResolver $parserServiceResolver,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'configuredparserserviceagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured parser service by id and delegates content parsing to the matching parser adapter.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);
		$this->service = null;
	}

	public function getPriority(): int {
		return $this->ensureService()->getPriority();
	}

	public function supports(AgentContentItem $item): bool {
		return $this->ensureService()->supports($item);
	}

	public function parse(AgentContentItem $item): AgentParsedContent {
		return $this->ensureService()->parse($item);
	}

	protected function ensureConfigured(): void {
		$this->ensureService();
	}

	protected function applyResolvedOptions(): void {
		if($this->service instanceof IParserService) {
			$this->service->setOptions($this->resolvedOptions);
		}
	}

	private function ensureService(): IParserService {
		if($this->service instanceof IParserService) {
			return $this->service;
		}

		$serviceId = $this->resolveServiceId();
		if($serviceId === '') {
			throw new RuntimeException('ConfiguredParserServiceAgentResource requires config key "service".');
		}

		$this->service = $this->parserServiceResolver->resolve($serviceId, $this->optionOverrides);
		$this->resolvedOptions = $this->service->getOptions();

		return $this->service;
	}
}
