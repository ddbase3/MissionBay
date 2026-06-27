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

namespace MissionBay;

use Base3\Api\ICheck;
use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Api\IRequest;
use Base3\ConfigValue\Api\IConfigValueResolver;
use Base3\ConfigValue\Resolver\ConfigValueResolver;
use Base3\Event\Api\IEventManager;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Agent\AgentConfigValueResolver;
use MissionBay\Agent\AgentContextFactory;
use MissionBay\Agent\AgentFlowFactory;
use MissionBay\Agent\AgentMemoryFactory;
use MissionBay\Agent\AgentNodeFactory;
use MissionBay\Agent\AgentRagPayloadNormalizer;
use MissionBay\Agent\AgentResourceFactory;
use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Api\IAgentAssistantFinalResponseService;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantMessageFactory;
use MissionBay\Api\IAgentAssistantToolSetupFactory;
use MissionBay\Api\IAgentAssistantTurnService;
use MissionBay\Api\IAgentComponentFlowBuilder;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigFormService;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentExecutionService;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentMemoryFactory;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentRouterFactory;
use MissionBay\Api\IAgentToolOrchestratorFactory;
use MissionBay\Orchestrator\AgentToolOrchestratorFactory;
use MissionBay\Profile\AgentAssistantToolSetupFactory;
use MissionBay\Service\AgentComponentFlowBuilder;
use MissionBay\Service\AgentComponentPresetRepository;
use MissionBay\Service\AgentConfigFormService;
use MissionBay\Service\AgentExecutionService;
use MissionBay\Service\Assistant\AgentAssistantFallbackBuilder;
use MissionBay\Service\Assistant\AgentAssistantFinalResponseService;
use MissionBay\Service\Assistant\AgentAssistantMemoryService;
use MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use MissionBay\Service\Assistant\AgentAssistantTurnService;

class MissionBayPlugin implements IPlugin, ICheck {

	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase

	public static function getName(): string {
		return "missionbayplugin";
	}

	// Implementation of IPlugin

	public function init() {
		$this->container
			->set(self::getName(), $this, IContainer::SHARED)

			->set(IConfigValueResolver::class, fn($c) => new ConfigValueResolver($c->get(IClassMap::class)), IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(IAgentContextFactory::class, fn($c) => new AgentContextFactory($c->get(IClassMap::class)), IContainer::SHARED)
			// ->set(IAgentRouterFactory::class, fn($c) => new AgentRouterFactory($c->get(IClassMap::class)), IContainer::SHARED)
			->set(IAgentMemoryFactory::class, fn($c) => new AgentMemoryFactory($c->get(IClassMap::class)), IContainer::SHARED)
			->set(IAgentNodeFactory::class, fn($c) => new AgentNodeFactory($c->get(IClassMap::class)), IContainer::SHARED)
			->set(IAgentResourceFactory::class, fn($c) => new AgentResourceFactory($c->get(IClassMap::class)), IContainer::SHARED)
			->set(IAgentConfigValueResolver::class, fn($c) => new AgentConfigValueResolver($c->get(IConfigValueResolver::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentFlowFactory::class, fn($c) => new AgentFlowFactory($c->get(IClassMap::class), $c->get(IAgentNodeFactory::class)), IContainer::SHARED)
			->set(IAgentComponentPresetRepository::class, fn($c) => new AgentComponentPresetRepository($c->get(ISettingsStore::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentComponentFlowBuilder::class, fn($c) => new AgentComponentFlowBuilder($c->get(IAgentComponentPresetRepository::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentExecutionService::class, fn($c) => new AgentExecutionService(
				$c->get(IAgentContextFactory::class),
				$c->get(IAgentFlowFactory::class),
				$c->get(IAgentComponentFlowBuilder::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentConfigFormService::class, fn($c) => new AgentConfigFormService($c->get(IRequest::class), $c->get(ISettingsStore::class), $c->get(IClassMap::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentRagPayloadNormalizer::class, fn() => new AgentRagPayloadNormalizer(), IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(IAgentAssistantMessageFactory::class, fn() => new AgentAssistantMessageFactory(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantMemoryService::class, fn($c) => new AgentAssistantMemoryService($c->get(IAgentAssistantMessageFactory::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantToolSetupFactory::class, fn() => new AgentAssistantToolSetupFactory(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentToolOrchestratorFactory::class, fn($c) => new AgentToolOrchestratorFactory($c->get(IEventManager::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantFallbackBuilder::class, fn() => new AgentAssistantFallbackBuilder(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantFinalResponseService::class, fn($c) => new AgentAssistantFinalResponseService($c->get(IAgentAssistantMessageFactory::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantTurnService::class, fn($c) => new AgentAssistantTurnService(
				$c->get(IAgentAssistantMemoryService::class),
				$c->get(IAgentAssistantMessageFactory::class),
				$c->get(IAgentAssistantToolSetupFactory::class),
				$c->get(IAgentToolOrchestratorFactory::class),
				$c->get(IAgentAssistantFallbackBuilder::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE);
	}

	// Implementation of ICheck

	public function checkDependencies() {
		return array(
			'assistantfoundationplugin_installed' => $this->container->get('assistantfoundationplugin') ? 'Ok' : 'assistantfoundationplugin not installed'
		);
	}
}
