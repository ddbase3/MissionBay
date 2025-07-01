<?php declare(strict_types=1);

namespace MissionBay;

use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentRouterFactory;
use MissionBay\Api\IAgentMemoryFactory;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Agent\AgentContextFactory;
// use MissionBay\Agent\AgentRouterFactory;
use MissionBay\Agent\AgentMemoryFactory;
use MissionBay\Agent\AgentFlowFactory;
use MissionBay\Agent\AgentNodeFactory;

class MissionBayPlugin implements IPlugin {

	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase

	public static function getName(): string {
		return "missionbayplugin";
	}

	// Implementation of IPlugin

	public function init() {
		$this->container
			->set(self::getName(), $this, IContainer::SHARED)

			->set(IAgentContextFactory::class, fn($c) => new AgentContextFactory($c->get(IClassMap::class)), IContainer::SHARED)
			// ->set(IAgentRouterFactory::class, fn($c) => new AgentRouterFactory($c->get(IClassMap::class)), IContainer::SHARED)
			->set(IAgentMemoryFactory::class, fn($c) => new AgentMemoryFactory($c->get(IClassMap::class)), IContainer::SHARED)
			->set(IAgentNodeFactory::class, fn($c) => new AgentNodeFactory($c->get(IClassMap::class)), IContainer::SHARED)
			->set(IAgentFlowFactory::class, fn($c) => new AgentFlowFactory($c->get(IClassMap::class), $c->get(IAgentNodeFactory::class)), IContainer::SHARED);
	}
}
