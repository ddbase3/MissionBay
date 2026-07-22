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
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use Base3\State\Api\IStateStore;
use Base3\Usermanager\Api\IUsermanager;
use AssistantFoundation\Api\IAgentActionPolicy;
use AssistantFoundation\Api\IAiModelConfigurationProvider;
use AssistantFoundation\Api\IAgentCapabilitySelector;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAgentSuspensionRepository;
use AssistantFoundation\Api\IAgentToolResultCache;
use MissionBay\Agent\AgentConfigValueResolver;
use MissionBay\Agent\AgentContextFactory;
use MissionBay\Agent\AgentFlowFactory;
use MissionBay\Agent\AgentNodeFactory;
use MissionBay\Agent\AgentRagPayloadNormalizer;
use MissionBay\Agent\AgentResourceFactory;
use MissionBay\Cache\AgentToolCacheKeyBuilder;
use MissionBay\Cache\StateStoreAgentToolResultCache;
use MissionBay\Capability\AgentCapabilityCatalogBuilder;
use MissionBay\Capability\AgentCapabilityDiscoveryService;
use MissionBay\Capability\HybridAgentCapabilitySelector;
use MissionBay\Capability\ProfileAwareAgentCapabilitySelector;
use MissionBay\Capability\SemanticAgentCapabilitySelector;
use MissionBay\Composition\AgentCompositionInspector;
use MissionBay\Api\IAgentAssistantContextContributionService;
use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Api\IAgentAssistantFinalResponseService;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantMessageFactory;
use MissionBay\Api\IAgentAssistantToolSetupFactory;
use MissionBay\Api\IAgentAssistantTurnService;
use MissionBay\Api\IAgentComponentFlowBuilder;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentMemoryRoleResolver;
use MissionBay\Api\IAgentModelDecisionStrategyResolver;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlowCompiler;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentRouterFactory;
use MissionBay\Listener\MissionBayToolEventDisplayListener;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\AgentStagePipelineResolver;
use MissionBay\Orchestrator\AgentStateSynchronizer;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\Decision\AgentModelDecisionStrategyResolver;
use MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use MissionBay\Orchestrator\Policy\ComponentAgentActionPolicyResolver;
use MissionBay\Orchestrator\Policy\IAgentActionPolicyResolver;
use MissionBay\Orchestrator\Service\AgentActionResumeService;
use MissionBay\Orchestrator\Service\AgentInteractionResponseResolver;
use MissionBay\Orchestrator\Service\AgentActionReviewService;
use MissionBay\Orchestrator\Service\AgentBudgetGuardService;
use MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use MissionBay\Orchestrator\Service\AgentContextAssessmentService;
use MissionBay\Orchestrator\Service\AgentContinuationDecisionService;
use MissionBay\Orchestrator\Service\AgentLoopProgressService;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentResultVerificationService;
use MissionBay\Orchestrator\Service\AgentSemanticVerificationService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Service\AgentToolResultCacheService;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentCapabilityDiscoveryStage;
use MissionBay\Orchestrator\Stage\AgentAiCapabilitySelectionStage;
use MissionBay\Orchestrator\Stage\AgentCapabilitySelectionStage;
use MissionBay\Orchestrator\Suspension\StateStoreAgentSuspensionRepository;
use MissionBay\Orchestrator\Stage\AgentContextCompactionStage;
use MissionBay\Orchestrator\Stage\AgentFinalAnswerStage;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use MissionBay\Orchestrator\Validation\JsonSchemaValidator;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use MissionBay\Policy\MutationApprovalAgentActionPolicy;
use MissionBay\Profile\AgentAssistantToolSetupFactory;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentMemoryProfileResolver;
use MissionBay\Profile\AgentToolProfileResolver;
use MissionBay\Service\AgentComponentFlowBuilder;
use MissionBay\Service\AgentComponentPresetMaterializer;
use MissionBay\Service\AgentComponentPresetToolTestService;
use MissionBay\Service\AgentComponentPresetRepository;
use MissionBay\Service\AgentConfigFormService;
use MissionBay\Service\ConfiguredAiModelConfigurationProvider;
use MissionBay\Service\AgentExecutionService;
use MissionBay\Service\AgentFlowCompiler;
use MissionBay\Service\Assistant\AgentAssistantContextContributionService;
use MissionBay\Service\Assistant\AgentAssistantFallbackBuilder;
use MissionBay\Service\Assistant\AgentAssistantFinalResponseService;
use MissionBay\Service\Assistant\AgentFinalResponseGuardService;
use MissionBay\Service\Assistant\AgentAssistantMemoryService;
use MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use MissionBay\Service\Assistant\AgentAssistantTurnService;
use MissionBay\Service\Memory\AgentMemoryRoleResolver;

class MissionBayPlugin implements IPlugin, ICheck {

	/**
	 * Ordered stage pipeline used when an assistant node receives no explicit
	 * stages input. Component definitions below only make stages available;
	 * this list activates them and defines their execution order.
	 *
	 * @var array<int,string>
	 */
	private const DEFAULT_AGENT_STAGE_IDS = [
		'capability-discovery',
		'capability-selection',
		'model-decision',
		'action-policy',
		'tool-execution',
		'context-compaction',
		'tool-observation',
		'semantic-verification'
	];

	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase

	public static function getName(): string {
		return "missionbayplugin";
	}

	// Implementation of IPlugin

	public function init() {
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
			->set(IAgentComponentPresetMaterializer::class, fn($c) => new AgentComponentPresetMaterializer(
				$c->get(IAgentComponentPresetRepository::class),
				$c->get(IAgentResourceFactory::class),
				$c->get(IAgentContextFactory::class),
				$c->get(ILogger::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentComponentFlowBuilder::class, fn($c) => new AgentComponentFlowBuilder($c->get(IAgentComponentPresetRepository::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentOrchestratorProfileRepository::class, fn($c) => new AgentOrchestratorProfileRepository(
				$c->get(ISettingsStore::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentToolProfileResolver::class, fn($c) => new AgentToolProfileResolver(
				$c->get(ISettingsStore::class),
				$c->get(IAgentComponentPresetRepository::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentMemoryProfileResolver::class, fn($c) => new AgentMemoryProfileResolver(
				$c->get(ISettingsStore::class),
				$c->get(IAgentComponentPresetRepository::class),
				$c->get(IAgentResourceFactory::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentContextProfileResolver::class, fn($c) => new AgentContextProfileResolver(
				$c->get(ISettingsStore::class),
				$c->get(IAgentComponentPresetRepository::class),
				$c->get(IAgentResourceFactory::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentFlowCompiler::class, fn($c) => new AgentFlowCompiler(
				$c->get(IAgentComponentFlowBuilder::class),
				$c->get(AgentOrchestratorProfileRepository::class),
				$c->get(AgentToolProfileResolver::class),
				$c->get(AgentMemoryProfileResolver::class),
				$c->get(AgentContextProfileResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentExecutionService::class, fn($c) => new AgentExecutionService(
				$c->get(IAgentContextFactory::class),
				$c->get(IAgentFlowFactory::class),
				$c->get(IAgentFlowCompiler::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentCompositionInspector::class, fn($c) => new AgentCompositionInspector(
				$c->get(ISettingsStore::class),
				$c->get(IAgentFlowCompiler::class),
				$c->get(IAgentContextFactory::class),
				$c->get(IAgentFlowFactory::class),
				$c->get(AgentOrchestratorProfileRepository::class),
				$c->get(AgentToolProfileResolver::class),
				$c->get(AgentMemoryProfileResolver::class),
				$c->get(AgentContextProfileResolver::class),
				$c->get(IAgentComponentPresetRepository::class),
				$c->get(AgentCapabilityDiscoveryService::class),
				$c->get(AgentCapabilityCatalogBuilder::class),
				$c->get(AgentStagePipelineResolver::class),
				$c->get(IAgentMemoryRoleResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(ConfiguredAiModelConfigurationProvider::class, fn($c) => new ConfiguredAiModelConfigurationProvider(
				$c->get(ISettingsStore::class),
				$c->get(IAgentConfigValueResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAiModelConfigurationProvider::class, fn($c) => $c->get(ConfiguredAiModelConfigurationProvider::class), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentConfigFormService::class, fn($c) => new AgentConfigFormService(
				$c->get(IRequest::class),
				$c->get(ISettingsStore::class),
				$c->get(IClassMap::class),
				$c->get(IComponentResolver::class),
				$c->get(AgentOrchestratorProfileRepository::class),
				$c->get(AgentToolProfileResolver::class),
				$c->get(AgentMemoryProfileResolver::class),
				$c->get(AgentContextProfileResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentRagPayloadNormalizer::class, fn() => new AgentRagPayloadNormalizer(), IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(AgentCapabilityCatalogBuilder::class, fn() => new AgentCapabilityCatalogBuilder(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentCapabilityDiscoveryService::class, fn($c) => new AgentCapabilityDiscoveryService(
				$c->get(IComponentResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(HybridAgentCapabilitySelector::class, fn() => new HybridAgentCapabilitySelector(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(SemanticAgentCapabilitySelector::class, fn($c) => new SemanticAgentCapabilitySelector(
				$c->get(HybridAgentCapabilitySelector::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(ProfileAwareAgentCapabilitySelector::class, fn($c) => new ProfileAwareAgentCapabilitySelector(
				$c->get(HybridAgentCapabilitySelector::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentCapabilitySelector::class, fn($c) => $c->get(ProfileAwareAgentCapabilitySelector::class), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentCapabilitySelectionGuardService::class, fn() => new AgentCapabilitySelectionGuardService(), IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(IAgentAssistantMessageFactory::class, fn() => new AgentAssistantMessageFactory(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentModelDecisionStrategyResolver::class, fn($c) => new AgentModelDecisionStrategyResolver(
				$c->get(IClassMap::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentMemoryRoleResolver::class, fn() => new AgentMemoryRoleResolver(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentStateSynchronizer::class, fn() => new AgentStateSynchronizer(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantContextContributionService::class, fn($c) => new AgentAssistantContextContributionService(
				$c->get(IAgentMemoryRoleResolver::class),
				$c->get(AgentStateSynchronizer::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantMemoryService::class, fn($c) => new AgentAssistantMemoryService(
				$c->get(IAgentAssistantMessageFactory::class),
				$c->get(IAgentMemoryRoleResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantToolSetupFactory::class, fn($c) => new AgentAssistantToolSetupFactory(
				$c->get(AgentCapabilityCatalogBuilder::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentActionFingerprint::class, fn() => new AgentActionFingerprint(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentToolDefinitionSemantics::class, fn() => new AgentToolDefinitionSemantics(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentMutationCommitGuardService::class, fn($c) => new AgentMutationCommitGuardService(
				$c->get(AgentActionFingerprint::class),
				$c->get(IEventManager::class),
				$c->get(AgentToolDefinitionSemantics::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentActionPolicyResolver::class, fn($c) => new ComponentAgentActionPolicyResolver(
				$c->get(IComponentResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentToolCacheKeyBuilder::class, fn() => new AgentToolCacheKeyBuilder(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentToolResultCache::class, fn($c) => new StateStoreAgentToolResultCache(
				$c->get(IStateStore::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentSuspensionRepository::class, fn($c) => new StateStoreAgentSuspensionRepository(
				$c->get(IStateStore::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentInteractionResponseResolver::class, fn() => new AgentInteractionResponseResolver(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentActionResumeService::class, fn($c) => new AgentActionResumeService(
				$c->get(AgentActionFingerprint::class),
				$c->get(IAgentSuspensionRepository::class),
				$c->get(IEventManager::class),
				$c->get(AgentInteractionResponseResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentActionReviewService::class, fn($c) => new AgentActionReviewService(
				$c->get(AgentActionFingerprint::class),
				$c->get(IAgentSuspensionRepository::class),
				900,
				$c->get(AgentMutationCommitGuardService::class),
				$c->get(IEventManager::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentBudgetGuardService::class, fn() => new AgentBudgetGuardService(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentContextAssessmentService::class, fn() => new AgentContextAssessmentService(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentContinuationDecisionService::class, fn() => new AgentContinuationDecisionService(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentLoopProgressService::class, fn() => new AgentLoopProgressService(1), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentResultVerificationService::class, fn() => new AgentResultVerificationService(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentSemanticVerificationService::class, fn() => new AgentSemanticVerificationService(60000, 12000), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(JsonSchemaValidator::class, fn() => new JsonSchemaValidator(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentToolContractValidationService::class, fn($c) => new AgentToolContractValidationService(
				$c->get(JsonSchemaValidator::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentToolResultCacheService::class, fn($c) => new AgentToolResultCacheService(
				$c->get(IAgentToolResultCache::class),
				$c->get(IEventManager::class),
				$c->get(AgentToolCacheKeyBuilder::class),
				$c->get(AgentMutationCommitGuardService::class),
				$c->get(AgentToolContractValidationService::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentComponentPresetToolTestService::class, fn($c) => new AgentComponentPresetToolTestService(
				$c->get(IAgentActionPolicyResolver::class),
				$c->get(AgentActionFingerprint::class),
				$c->get(AgentActionReviewService::class),
				$c->get(AgentActionResumeService::class),
				$c->get(AgentToolContractValidationService::class),
				$c->get(AgentCapabilitySelectionGuardService::class),
				$c->get(AgentMutationCommitGuardService::class),
				$c->get(IEventManager::class),
				$c->get(AgentToolDefinitionSemantics::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentStagePipelineResolver::class, fn($c) => new AgentStagePipelineResolver(
				$c->get(IComponentResolver::class),
				self::DEFAULT_AGENT_STAGE_IDS
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentToolOrchestrator::class, fn($c) => new AgentToolOrchestrator(
				null,
				null,
				null,
				$c->get(AgentActionResumeService::class),
				$c->get(AgentBudgetGuardService::class),
				$c->get(AgentLoopProgressService::class),
				$c->get(AgentStateSynchronizer::class),
				$c->get(AgentToolDefinitionSemantics::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantFallbackBuilder::class, fn() => new AgentAssistantFallbackBuilder(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentFinalResponseGuardService::class, fn() => new AgentFinalResponseGuardService(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantFinalResponseService::class, fn($c) => new AgentAssistantFinalResponseService(
				$c->get(IAgentAssistantMessageFactory::class),
				$c->get(AgentFinalResponseGuardService::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentAssistantTurnService::class, fn($c) => new AgentAssistantTurnService(
				$c->get(IAgentAssistantMemoryService::class),
				$c->get(IAgentAssistantContextContributionService::class),
				$c->get(IAgentAssistantMessageFactory::class),
				$c->get(IAgentAssistantToolSetupFactory::class),
				$c->get(AgentCapabilityDiscoveryService::class),
				$c->get(AgentStagePipelineResolver::class),
				$c->get(AgentToolOrchestrator::class),
				$c->get(AgentActionResumeService::class),
				$c->get(IAgentAssistantFallbackBuilder::class),
				$c->get(AgentStateSynchronizer::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(MissionBayToolEventDisplayListener::class, fn($c) => new MissionBayToolEventDisplayListener(
				$c->get(IDatabase::class),
				$c->get(IUsermanager::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE);

		$this->registerDefaultAgentStageDefinitions();
	}

	private function registerDefaultAgentStageDefinitions(): void {
		$this->registerAgentActionPolicyDefinition(new ComponentDefinition(
			id: 'mutation-approval-actions',
			interfaceName: IAgentActionPolicy::class,
			implementationName: MutationApprovalAgentActionPolicy::getName(),
			arguments: [
				'id' => 'mutation-approval-actions',
				'policyName' => 'mutation-approval-actions'
			]
		));

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
			id: 'capability-discovery',
			interfaceName: IAgentStage::class,
			implementationName: AgentCapabilityDiscoveryStage::getName(),
			arguments: [
				'id' => 'capability-discovery',
				'stageName' => 'capability-discovery'
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'capability-selection',
			interfaceName: IAgentStage::class,
			implementationName: AgentCapabilitySelectionStage::getName(),
			arguments: [
				'id' => 'capability-selection',
				'stageName' => 'capability-selection',
				'selector' => $this->container->get(ProfileAwareAgentCapabilitySelector::class)
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'ai-capability-selection',
			interfaceName: IAgentStage::class,
			implementationName: AgentAiCapabilitySelectionStage::getName(),
			arguments: [
				'id' => 'ai-capability-selection',
				'stageName' => 'ai-capability-selection',
				'selector' => $this->container->get(SemanticAgentCapabilitySelector::class)
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'model-decision',
			interfaceName: IAgentStage::class,
			implementationName: AgentModelDecisionStage::getName(),
			arguments: [
				'id' => 'model-decision',
				'stageName' => 'model-decision',
				'strategyResolver' => $this->container->get(IAgentModelDecisionStrategyResolver::class)
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'action-policy',
			interfaceName: IAgentStage::class,
			implementationName: AgentActionPolicyStage::getName(),
			arguments: [
				'id' => 'action-policy',
				'stageName' => 'action-policy',
				'policyIds' => ['mutation-approval-actions', 'allow-all-actions'],
				'actionReviewService' => $this->container->get(AgentActionReviewService::class),
				'toolContractValidationService' => $this->container->get(AgentToolContractValidationService::class),
				'capabilitySelectionGuardService' => $this->container->get(AgentCapabilitySelectionGuardService::class)
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'tool-execution',
			interfaceName: IAgentStage::class,
			implementationName: AgentToolExecutionStage::getName(),
			arguments: [
				'id' => 'tool-execution',
				'stageName' => 'tool-execution',
				'toolResultCacheService' => $this->container->get(AgentToolResultCacheService::class),
				'budgetGuardService' => $this->container->get(AgentBudgetGuardService::class),
				'resultVerificationService' => $this->container->get(AgentResultVerificationService::class),
				'mutationCommitGuardService' => $this->container->get(AgentMutationCommitGuardService::class),
				'toolContractValidationService' => $this->container->get(AgentToolContractValidationService::class),
				'capabilitySelectionGuardService' => $this->container->get(AgentCapabilitySelectionGuardService::class)
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
				'targetSummaryCharacters' => 4000,
				'contextAssessmentService' => $this->container->get(AgentContextAssessmentService::class)
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
			id: 'semantic-verification',
			interfaceName: IAgentStage::class,
			implementationName: AgentSemanticVerificationStage::getName(),
			arguments: [
				'id' => 'semantic-verification',
				'stageName' => 'semantic-verification',
				'verificationService' => $this->container->get(AgentSemanticVerificationService::class),
				'continuationDecisionService' => $this->container->get(AgentContinuationDecisionService::class)
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
