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
use Base3\Api\IComponentResolver;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Api\IRequest;
use Base3\ConfigValue\Api\IConfigValueResolver;
use Base3\ConfigValue\Resolver\ConfigValueResolver;
use Base3\Core\ComponentDefinition;
use Base3\Database\Api\IDatabase;
use Base3\Event\Api\IEventManager;
use Base3\Event\EventManager;
use Base3\Settings\Api\ISettingsStore;
use Base3\State\Api\IStateStore;
use Base3\Usermanager\Api\IUsermanager;
use AssistantFoundation\Api\IAgentActionPolicy;
use AssistantFoundation\Api\IAgentExecutionService;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAgentToolResultCache;
use MissionBay\Agent\AgentConfigValueResolver;
use MissionBay\Agent\AgentContextFactory;
use MissionBay\Agent\AgentFlowFactory;
use MissionBay\Agent\AgentNodeFactory;
use MissionBay\Agent\AgentRagPayloadNormalizer;
use MissionBay\Agent\AgentResourceFactory;
use MissionBay\Cache\AgentToolCacheKeyBuilder;
use MissionBay\Cache\NullAgentToolResultCache;
use MissionBay\Cache\StateStoreAgentToolResultCache;
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
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentRouterFactory;
use MissionBay\Listener\MissionBayToolEventDisplayListener;
use MissionBay\Orchestrator\AgentStagePipelineResolver;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\Policy\ComponentAgentActionPolicyResolver;
use MissionBay\Orchestrator\Policy\IAgentActionPolicyResolver;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentBudgetGuardStage;
use MissionBay\Orchestrator\Stage\AgentContextAssessmentStage;
use MissionBay\Orchestrator\Stage\AgentContextCompactionStage;
use MissionBay\Orchestrator\Stage\AgentContinuationDecisionStage;
use MissionBay\Orchestrator\Stage\AgentFinalAnswerStage;
use MissionBay\Orchestrator\Stage\AgentLoopProgressStage;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentResultVerificationStage;
use MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use MissionBay\Orchestrator\Stage\AgentToolResultCacheStage;
use MissionBay\Policy\AllowAllAgentActionPolicy;
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

	/**
	 * Ordered stage pipeline used when an assistant node receives no explicit
	 * stages input. Component definitions below only make stages available;
	 * this list activates them and defines their execution order.
	 *
	 * @var array<int,string>
	 */
	private const DEFAULT_AGENT_STAGE_IDS = [
		'budget-guard',
		'model-decision',
		'action-policy',
		'tool-cache-lookup',
		'tool-budget-guard',
		'tool-execution',
		'context-assessment',
		'result-verification',
		'tool-cache-store',
		'tool-observation',
		'loop-progress',
		'semantic-verification',
		'continuation-decision',
		'final-budget-guard'
	];

	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase

	public static function getName(): string {
		return "missionbayplugin";
	}

	// Implementation of IPlugin

	public function init() {
		$this->registerDefaultAgentStageDefinitions();

		$this->container
			->set(self::getName(), $this, IContainer::SHARED)

			->set(IEventManager::class, fn() => new EventManager(), IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(IConfigValueResolver::class, fn($c) => new ConfigValueResolver($c->get(IClassMap::class)), IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(IAgentContextFactory::class, fn($c) => new AgentContextFactory($c->get(IClassMap::class)), IContainer::SHARED)
			// ->set(IAgentRouterFactory::class, fn($c) => new AgentRouterFactory($c->get(IClassMap::class)), IContainer::SHARED)
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
			->set(IAgentActionPolicyResolver::class, fn($c) => new ComponentAgentActionPolicyResolver(
				$c->get(IComponentResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentToolCacheKeyBuilder::class, fn() => new AgentToolCacheKeyBuilder(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentToolResultCache::class, function($c): IAgentToolResultCache {
				if ($c->has(IStateStore::class)) {
					return new StateStoreAgentToolResultCache($c->get(IStateStore::class));
				}

				return new NullAgentToolResultCache();
			}, IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentStagePipelineResolver::class, fn($c) => new AgentStagePipelineResolver(
				$c->get(IComponentResolver::class),
				self::DEFAULT_AGENT_STAGE_IDS
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentToolOrchestrator::class, fn() => new AgentToolOrchestrator(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantFallbackBuilder::class, fn() => new AgentAssistantFallbackBuilder(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantFinalResponseService::class, fn($c) => new AgentAssistantFinalResponseService($c->get(IAgentAssistantMessageFactory::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantTurnService::class, fn($c) => new AgentAssistantTurnService(
				$c->get(IAgentAssistantMemoryService::class),
				$c->get(IAgentAssistantMessageFactory::class),
				$c->get(IAgentAssistantToolSetupFactory::class),
				$c->get(AgentStagePipelineResolver::class),
				$c->get(AgentToolOrchestrator::class),
				$c->get(IAgentAssistantFallbackBuilder::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(MissionBayToolEventDisplayListener::class, fn($c) => new MissionBayToolEventDisplayListener(
				$c->get(IDatabase::class),
				$c->get(IUsermanager::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE);
	}

	private function registerDefaultAgentStageDefinitions(): void {
		$this->registerAgentActionPolicyDefinition(new ComponentDefinition(
			id: 'allow-all-actions',
			interfaceName: IAgentActionPolicy::class,
			implementationName: AllowAllAgentActionPolicy::getName(),
			arguments: [
				'id' => 'allow-all-actions',
				'policyName' => 'allow-all-actions'
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'budget-guard',
			interfaceName: IAgentStage::class,
			implementationName: AgentBudgetGuardStage::getName(),
			arguments: [
				'id' => 'budget-guard',
				'stageName' => 'budget-guard'
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'model-decision',
			interfaceName: IAgentStage::class,
			implementationName: AgentModelDecisionStage::getName(),
			arguments: [
				'id' => 'model-decision',
				'stageName' => 'model-decision'
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'action-policy',
			interfaceName: IAgentStage::class,
			implementationName: AgentActionPolicyStage::getName(),
			arguments: [
				'id' => 'action-policy',
				'stageName' => 'action-policy',
				'policyIds' => ['allow-all-actions']
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'tool-cache-lookup',
			interfaceName: IAgentStage::class,
			implementationName: AgentToolResultCacheStage::getName(),
			arguments: [
				'id' => 'tool-cache-lookup',
				'stageName' => 'tool-cache-lookup',
				'checkpoint' => AgentToolResultCacheStage::CHECKPOINT_LOOKUP
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'tool-budget-guard',
			interfaceName: IAgentStage::class,
			implementationName: AgentBudgetGuardStage::getName(),
			arguments: [
				'id' => 'tool-budget-guard',
				'stageName' => 'tool-budget-guard',
				'checkpoint' => AgentBudgetGuardStage::CHECKPOINT_TOOLS
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'tool-execution',
			interfaceName: IAgentStage::class,
			implementationName: AgentToolExecutionStage::getName(),
			arguments: [
				'id' => 'tool-execution',
				'stageName' => 'tool-execution'
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'context-assessment',
			interfaceName: IAgentStage::class,
			implementationName: AgentContextAssessmentStage::getName(),
			arguments: [
				'id' => 'context-assessment',
				'stageName' => 'context-assessment'
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'context-compaction',
			interfaceName: IAgentStage::class,
			implementationName: AgentContextCompactionStage::getName(),
			arguments: [
				'id' => 'context-compaction',
				'stageName' => 'context-compaction',
				'minToolResultBytes' => 12000,
				'maxInputBytes' => 80000,
				'targetSummaryCharacters' => 4000
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'result-verification',
			interfaceName: IAgentStage::class,
			implementationName: AgentResultVerificationStage::getName(),
			arguments: [
				'id' => 'result-verification',
				'stageName' => 'result-verification'
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'semantic-verification',
			interfaceName: IAgentStage::class,
			implementationName: AgentSemanticVerificationStage::getName(),
			arguments: [
				'id' => 'semantic-verification',
				'stageName' => 'semantic-verification',
				'maxInputBytes' => 60000,
				'maxTaskBytes' => 12000
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'tool-cache-store',
			interfaceName: IAgentStage::class,
			implementationName: AgentToolResultCacheStage::getName(),
			arguments: [
				'id' => 'tool-cache-store',
				'stageName' => 'tool-cache-store',
				'checkpoint' => AgentToolResultCacheStage::CHECKPOINT_STORE
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'tool-observation',
			interfaceName: IAgentStage::class,
			implementationName: AgentToolObservationStage::getName(),
			arguments: [
				'id' => 'tool-observation',
				'stageName' => 'tool-observation'
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'loop-progress',
			interfaceName: IAgentStage::class,
			implementationName: AgentLoopProgressStage::getName(),
			arguments: [
				'id' => 'loop-progress',
				'stageName' => 'loop-progress',
				'maxConsecutiveStalledIterations' => 1
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'continuation-decision',
			interfaceName: IAgentStage::class,
			implementationName: AgentContinuationDecisionStage::getName(),
			arguments: [
				'id' => 'continuation-decision',
				'stageName' => 'continuation-decision',
				'minAnswerConfidence' => 0.75,
				'minClarifyConfidence' => 0.75,
				'minContinueConfidence' => 0.70
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'final-budget-guard',
			interfaceName: IAgentStage::class,
			implementationName: AgentBudgetGuardStage::getName(),
			arguments: [
				'id' => 'final-budget-guard',
				'stageName' => 'final-budget-guard',
				'checkpoint' => AgentBudgetGuardStage::CHECKPOINT_FINAL
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'final-answer-regenerate',
			interfaceName: IAgentStage::class,
			implementationName: AgentFinalAnswerStage::getName(),
			arguments: [
				'id' => 'final-answer-regenerate',
				'stageName' => 'final-answer-regenerate',
				'mode' => AgentFinalAnswerStage::MODE_REGENERATE
			]
		));
	}

	private function registerAgentActionPolicyDefinition(ComponentDefinition $definition): void {
		$this->container->set(
			$definition->getServiceName(),
			$definition,
			IContainer::PARAMETER | IContainer::NOOVERWRITE
		);
	}

	private function registerAgentStageDefinition(ComponentDefinition $definition): void {
		$this->container->set(
			$definition->getServiceName(),
			$definition,
			IContainer::PARAMETER | IContainer::NOOVERWRITE
		);
	}

	// Implementation of ICheck

	public function checkDependencies() {
		return array(
			'assistantfoundationplugin_installed' => $this->container->has('assistantfoundationplugin') ? 'Ok' : 'assistantfoundationplugin not installed'
		);
	}
}
