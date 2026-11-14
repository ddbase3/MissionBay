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

namespace Base3\MissionBay;

use Base3\CoreFoundation\Api\ICheck;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Api\IComponentResolver;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Api\IPlugin;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\ConfigValue\Api\IConfigValueResolver;
use Base3\Core\ConfigValue\Resolver\ConfigValueResolver;
use Base3\CoreFoundation\Dto\ComponentDefinition;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\Core\Event\EventManager;
use Base3\CoreFoundation\Language\Api\ILanguage;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\LinkTarget\Api\ILinkTargetService;
use Base3\ClientStack\FileManager\FileManagerHttpService;
use Base3\MissionBay\Output\AgentFileManagerOutput;
use Base3\ResourceFoundation\Api\IManagedFileStorageService;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\AssistantFoundation\Api\IAgentActionPolicy;
use Base3\AssistantFoundation\Api\IAgentConfigurationService;
use Base3\AssistantFoundation\Api\IRetrievalCollectionDefinition;
use Base3\AssistantFoundation\Api\IAiModelConfigurationProvider;
use Base3\AssistantFoundation\Api\IRealtimeSpeechToTextSessionService;
use Base3\AssistantFoundation\Api\ITextToSpeechService;
use Base3\AssistantFoundation\Api\IAgentCapabilitySelector;
use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Api\IAgentSuspensionRepository;
use Base3\AssistantFoundation\Api\IAgentToolResultCache;
use Base3\CredentialFoundation\Api\ICredentialAccess;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\Agent\AgentConfigValueResolver;
use Base3\MissionBay\Agent\AgentContextFactory;
use Base3\MissionBay\Agent\AgentFlowFactory;
use Base3\MissionBay\Agent\AgentNodeFactory;
use Base3\MissionBay\Agent\AgentResourceFactory;
use Base3\MissionBay\Cache\AgentToolCacheKeyBuilder;
use Base3\MissionBay\Cache\StateStoreAgentToolResultCache;
use Base3\MissionBay\Context\Profile\MissionBayContextProfileProvider;
use Base3\MissionBay\Capability\AgentCapabilityCatalogBuilder;
use Base3\MissionBay\Capability\AgentCapabilityDiscoveryService;
use Base3\MissionBay\Capability\HybridAgentCapabilitySelector;
use Base3\MissionBay\Capability\ProfileAwareAgentCapabilitySelector;
use Base3\MissionBay\Capability\SemanticAgentCapabilitySelector;
use Base3\MissionBay\Composition\AgentCompositionInspector;
use Base3\MissionBay\Api\IAgentAssistantContextContributionService;
use Base3\MissionBay\Api\IAgentAssistantFallbackBuilder;
use Base3\MissionBay\Api\IAgentAssistantFinalResponseService;
use Base3\MissionBay\Api\IAgentAssistantMemoryService;
use Base3\MissionBay\Api\IAgentAssistantMessageFactory;
use Base3\MissionBay\Api\IAgentAssistantToolSetupFactory;
use Base3\MissionBay\Api\IAgentAssistantTurnService;
use Base3\MissionBay\Api\IAgentComponentFlowBuilder;
use Base3\MissionBay\Api\IAgentComponentPresetCatalog;
use Base3\MissionBay\Api\IAgentComponentPresetFlowExpander;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentMemoryRoleResolver;
use Base3\MissionBay\Api\IAgentModelDecisionStrategyResolver;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IConfiguredParserServiceResolver;
use Base3\MissionBay\Api\IParserServiceTestService;
use Base3\MissionBay\Api\IAgentContextFactory;
use Base3\MissionBay\Api\IAgentFlowCompiler;
use Base3\MissionBay\Api\IAgentFlowFactory;
use Base3\MissionBay\Api\IAgentNodeFactory;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Api\IAgentRouterFactory;
use Base3\MissionBay\Api\IMcpClientFactory;
use Base3\MissionBay\Api\IMcpTransport;
use Base3\MissionBay\Api\IRetrievalSearchService;
use Base3\MissionBay\Api\IEmbeddingOrchestratorConfigRepository;
use Base3\MissionBay\Api\IRetrievalCollectionConfigRepository;
use Base3\MissionBay\Listener\MissionBayAiUsageLogListener;
use Base3\MissionBay\Listener\MissionBayToolEventDisplayListener;
use Base3\MissionBay\Mcp\Client\McpClientFactory;
use Base3\MissionBay\Mcp\Client\McpRemoteToolDefinitionMapper;
use Base3\MissionBay\Mcp\Client\McpRemoteToolResultMapper;
use Base3\MissionBay\Mcp\Client\McpHmacRequestSigner;
use Base3\MissionBay\Mcp\Client\McpStreamableHttpTransport;
use Base3\MissionBay\Mcp\McpProfileAuthorizer;
use Base3\MissionBay\Mcp\McpToolProfileRepository;
use Base3\MissionBay\Reporting\Settings\AiUsageReportingSettingsSeeder;
use Base3\MissionBay\Retrieval\DefaultRetrievalCollectionDefinition;
use Base3\MissionBay\Retrieval\PhoneticTextMaterializer;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentStagePipelineResolver;
use Base3\MissionBay\Orchestrator\AgentStateSynchronizer;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Orchestrator\Decision\AgentModelDecisionStrategyResolver;
use Base3\MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use Base3\MissionBay\Orchestrator\Policy\ComponentAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Policy\IAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Service\AgentBatchExecutionService;
use Base3\MissionBay\Orchestrator\Service\AgentBatchResultService;
use Base3\MissionBay\Orchestrator\Service\AgentInteractionResponseResolver;
use Base3\MissionBay\Orchestrator\Service\AgentActionReviewService;
use Base3\MissionBay\Orchestrator\Service\AgentBudgetGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentContextAssessmentService;
use Base3\MissionBay\Orchestrator\Service\AgentContinuationDecisionService;
use Base3\MissionBay\Orchestrator\Service\AgentLoopProgressService;
use Base3\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentResultVerificationService;
use Base3\MissionBay\Orchestrator\Service\AgentSemanticVerificationService;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use Base3\MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use Base3\MissionBay\Orchestrator\Service\AgentToolResultCacheService;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Orchestrator\Stage\AgentCapabilityDiscoveryStage;
use Base3\MissionBay\Orchestrator\Stage\AgentAiCapabilitySelectionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentCapabilitySelectionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentContextCompactionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentFinalAnswerStage;
use Base3\MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use Base3\MissionBay\Orchestrator\Suspension\UnavailableAgentSuspensionRepository;
use Base3\MissionBay\Orchestrator\Validation\JsonSchemaValidator;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use Base3\MissionBay\Policy\MutationApprovalAgentActionPolicy;
use Base3\MissionBay\ParserService\ConfiguredParserServiceResolver;
use Base3\MissionBay\ParserService\ParserServiceTestService;
use Base3\MissionBay\Profile\AgentAssistantToolSetupFactory;
use Base3\MissionBay\Profile\AgentContextProfileResolver;
use Base3\MissionBay\Profile\AgentMemoryProfileResolver;
use Base3\MissionBay\Profile\AgentToolProfileResolver;
use Base3\MissionBay\Service\AgentComponentFlowBuilder;
use Base3\MissionBay\Service\AiProviderFileOwnerCleanupService;
use Base3\MissionBay\Service\AiProviderFileReferenceStateRepository;
use Base3\MissionBay\Service\AgentComponentPresetCatalog;
use Base3\MissionBay\Service\AgentComponentPresetFlowExpander;
use Base3\MissionBay\Service\AgentComponentPresetMaterializer;
use Base3\MissionBay\Service\AgentComponentPresetToolTestService;
use Base3\MissionBay\Service\AgentComponentPresetRepository;
use Base3\MissionBay\Service\RetrievalSearchService;
use Base3\MissionBay\Service\EmbeddingOrchestratorConfigRepository;
use Base3\MissionBay\Service\RetrievalCollectionConfigRepository;
use Base3\MissionBay\Service\AgentConfigFormService;
use Base3\MissionBay\Service\AgentConfigurationService;
use Base3\MissionBay\Service\ConfiguredAiModelConfigurationProvider;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use Base3\MissionBay\Speech\ConfiguredRealtimeSpeechToTextSessionService;
use Base3\MissionBay\Speech\ConfiguredTextToSpeechService;
use Base3\MissionBay\Tool\Profile\MissionBayAgentToolProfileProvider;
use Base3\MissionBay\Service\AgentConversationService;
use Base3\MissionBay\Service\AgentExecutionService;
use Base3\MissionBay\Service\AgentTextTaskService;
use Base3\MissionBay\Service\AgentFlowCompiler;
use Base3\MissionBay\Service\Assistant\AgentAssistantContextContributionService;
use Base3\MissionBay\Service\Assistant\AgentAssistantFallbackBuilder;
use Base3\MissionBay\Service\Assistant\AgentAssistantFinalResponseService;
use Base3\MissionBay\Service\Assistant\AgentFinalResponseGuardService;
use Base3\MissionBay\Service\Assistant\AgentAssistantMemoryService;
use Base3\MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use Base3\MissionBay\Service\Assistant\AgentAssistantTurnService;
use Base3\MissionBay\Service\Memory\AgentMemoryRoleResolver;

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
			->set('agentAdminDisplayData', fn($c) => $c->has(IManagedFileStorageService::class)
				? [ 'resources' => [
					'enabled' => true,
					'endpoints' => FileManagerHttpService::buildEndpoints(
						$c->get(ILinkTargetService::class)->getLink([
							'name' => AgentFileManagerOutput::getName(),
							'out' => 'php'
						], ['fm_group' => 'agent'])
					),
					'max_file_size' => FileManagerHttpService::DEFAULT_MAX_FILE_SIZE,
					'accept' => 'application/pdf,.pdf'
				]]
				: [], IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AiUsageReportingSettingsSeeder::class, fn($c) => new AiUsageReportingSettingsSeeder(
				$c->get(ISettingsStore::class),
				dirname(__DIR__)
			), IContainer::SHARED)

			->set(IEventManager::class, fn() => new EventManager(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AiProviderRequestEventDispatcher::class, fn($c) => new AiProviderRequestEventDispatcher(
				$c->get(IEventManager::class)
			), IContainer::SHARED)

			->set(IConfigValueResolver::class, fn($c) => new ConfigValueResolver($c->get(IClassMap::class)), IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(IAgentContextFactory::class, fn($c) => new AgentContextFactory($c->get(IClassMap::class)), IContainer::SHARED)
			// ->set(IAgentRouterFactory::class, fn($c) => new AgentRouterFactory($c->get(IClassMap::class)), IContainer::SHARED)
			->set(IAgentNodeFactory::class, fn($c) => new AgentNodeFactory($c->get(IClassMap::class)), IContainer::SHARED)
			->set(IAgentResourceFactory::class, fn($c) => new AgentResourceFactory($c->get(IClassMap::class)), IContainer::SHARED)
			->set(IAgentConfigValueResolver::class, fn($c) => new AgentConfigValueResolver($c->get(IConfigValueResolver::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(ConfiguredServiceRuntimeResolver::class, fn($c) => new ConfiguredServiceRuntimeResolver(
				$c->get(ISettingsStore::class),
				$c->get(IClassMap::class),
				$c->get(IAgentConfigValueResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AiProviderFileReferenceStateRepository::class, fn($c) => new AiProviderFileReferenceStateRepository(
				$c->get(IStateStore::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AiProviderFileOwnerCleanupService::class, fn($c) => new AiProviderFileOwnerCleanupService(
				$c->get(AiProviderFileReferenceStateRepository::class),
				$c->get(ConfiguredServiceRuntimeResolver::class),
				$c->get(ISettingsStore::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IConfiguredParserServiceResolver::class, fn($c) => new ConfiguredParserServiceResolver(
				$c->get(ConfiguredServiceRuntimeResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IParserServiceTestService::class, fn($c) => new ParserServiceTestService(
				$c->get(ILogger::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(IMcpTransport::class, fn() => new McpStreamableHttpTransport(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(McpHmacRequestSigner::class, fn() => new McpHmacRequestSigner(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IMcpClientFactory::class, fn($c) => new McpClientFactory(
				$c->get(IMcpTransport::class),
				$c->get(McpHmacRequestSigner::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(McpToolProfileRepository::class, fn($c) => new McpToolProfileRepository(
				$c->get(ISettingsStore::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(McpProfileAuthorizer::class, fn($c) => new McpProfileAuthorizer(
				$c->get(IRequest::class),
				$c->get(McpToolProfileRepository::class),
				$c->get(ILogger::class),
				$c->has(ICredentialAccess::class) ? $c->get(ICredentialAccess::class) : null
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(McpRemoteToolDefinitionMapper::class, fn() => new McpRemoteToolDefinitionMapper(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(McpRemoteToolResultMapper::class, fn() => new McpRemoteToolResultMapper(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentFlowFactory::class, fn($c) => new AgentFlowFactory($c->get(IClassMap::class), $c->get(IAgentNodeFactory::class)), IContainer::SHARED)
			->set(IAgentComponentPresetRepository::class, fn($c) => new AgentComponentPresetRepository($c->get(ISettingsStore::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentComponentPresetCatalog::class, fn($c) => new AgentComponentPresetCatalog(
				$c->get(IAgentComponentPresetRepository::class),
				$c->get(IClassMap::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentComponentPresetFlowExpander::class, fn($c) => new AgentComponentPresetFlowExpander(
				$c->get(IAgentComponentPresetRepository::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IEmbeddingOrchestratorConfigRepository::class, fn($c) => new EmbeddingOrchestratorConfigRepository(
				$c->get(ISettingsStore::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IRetrievalCollectionConfigRepository::class, fn($c) => new RetrievalCollectionConfigRepository(
				$c->get(ISettingsStore::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentComponentPresetMaterializer::class, fn($c) => new AgentComponentPresetMaterializer(
				$c->get(IAgentComponentPresetRepository::class),
				$c->get(IAgentResourceFactory::class),
				$c->get(IAgentContextFactory::class),
				$c->get(ILogger::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentComponentFlowBuilder::class, fn($c) => new AgentComponentFlowBuilder(
				$c->get(IAgentComponentPresetRepository::class),
				$c->get(IAgentComponentPresetFlowExpander::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentConfigurationService::class, fn($c) => new AgentConfigurationService(
				$c->get(ISettingsStore::class),
				$c->get(IClassMap::class),
				$c->has(IManagedFileStorageService::class)
					? $c->get(IManagedFileStorageService::class) : null,
				$c->get(\Base3\ClientStack\FileManager\FileUploadService::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
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
			->set(MissionBayContextProfileProvider::class, fn($c) => new MissionBayContextProfileProvider(
				$c->get(AgentContextProfileResolver::class),
				$c->get(IAgentComponentPresetMaterializer::class)
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
			->set(AgentConversationService::class, fn($c) => new AgentConversationService(
				$c->get(AgentMemoryProfileResolver::class),
				$c->get(IAgentComponentPresetMaterializer::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentTextTaskService::class, fn($c) => new AgentTextTaskService(
				$c->get(IAgentFlowCompiler::class),
				$c->get(IAgentFlowFactory::class),
				$c->get(IAgentComponentPresetMaterializer::class),
				$c->get(AgentContextProfileResolver::class),
				$c->get(AgentToolProfileResolver::class),
				$c->get(IAgentAssistantContextContributionService::class)
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
				$c->get(IAgentConfigValueResolver::class),
				$c->get(IClassMap::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAiModelConfigurationProvider::class, fn($c) => $c->get(ConfiguredAiModelConfigurationProvider::class), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IRealtimeSpeechToTextSessionService::class, fn($c) => new ConfiguredRealtimeSpeechToTextSessionService(
				$c->get(ConfiguredServiceRuntimeResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(ITextToSpeechService::class, fn($c) => new ConfiguredTextToSpeechService(
				$c->get(ConfiguredServiceRuntimeResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentConfigFormService::class, fn($c) => new AgentConfigFormService(
				$c->get(IRequest::class),
				$c->get(ILanguage::class),
				$c->get(ISettingsStore::class),
				$c->get(IClassMap::class),
				$c->get(IComponentResolver::class),
				$c->get(AgentOrchestratorProfileRepository::class),
				$c->get(AgentToolProfileResolver::class),
				$c->get(AgentMemoryProfileResolver::class),
				$c->get(AgentContextProfileResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IRetrievalCollectionDefinition::class, fn($c) => new DefaultRetrievalCollectionDefinition(
				$c->get(IRetrievalCollectionConfigRepository::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(PhoneticTextMaterializer::class, fn($c) => new PhoneticTextMaterializer(
				$c->get(IClassMap::class),
				$c->get(IRetrievalCollectionDefinition::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)

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
			->set(AgentBatchExecutionService::class, fn($c) => new AgentBatchExecutionService(
				$c->get(AgentMutationCommitGuardService::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentBatchResultService::class, fn() => new AgentBatchResultService(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentActionPolicyResolver::class, fn($c) => new ComponentAgentActionPolicyResolver(
				$c->get(IComponentResolver::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentToolCacheKeyBuilder::class, fn() => new AgentToolCacheKeyBuilder(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IAgentToolResultCache::class, fn($c) => new StateStoreAgentToolResultCache(
				$c->get(IStateStore::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentInteractionResponseResolver::class, fn() => new AgentInteractionResponseResolver(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(UnavailableAgentSuspensionRepository::class, fn() => new UnavailableAgentSuspensionRepository(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentActionResumeService::class, fn($c) => new AgentActionResumeService(
				$c->get(AgentActionFingerprint::class),
				$c->has(IAgentSuspensionRepository::class)
					? $c->get(IAgentSuspensionRepository::class)
					: $c->get(UnavailableAgentSuspensionRepository::class),
				$c->get(IEventManager::class),
				$c->get(AgentInteractionResponseResolver::class),
				$c->get(AgentBatchExecutionService::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentActionReviewService::class, fn($c) => new AgentActionReviewService(
				$c->get(AgentActionFingerprint::class),
				$c->has(IAgentSuspensionRepository::class)
					? $c->get(IAgentSuspensionRepository::class)
					: $c->get(UnavailableAgentSuspensionRepository::class),
				900,
				$c->get(AgentMutationCommitGuardService::class),
				$c->get(IEventManager::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentBudgetGuardService::class, fn() => new AgentBudgetGuardService(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentContextAssessmentService::class, fn() => new AgentContextAssessmentService(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentContinuationDecisionService::class, fn() => new AgentContinuationDecisionService(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentLoopProgressService::class, fn() => new AgentLoopProgressService(2), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentResultVerificationService::class, fn() => new AgentResultVerificationService(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentSemanticVerificationService::class, fn() => new AgentSemanticVerificationService(60000, 12000), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(JsonSchemaValidator::class, fn() => new JsonSchemaValidator(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentToolContractValidationService::class, fn($c) => new AgentToolContractValidationService(
				$c->get(JsonSchemaValidator::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(MissionBayAgentToolProfileProvider::class, fn($c) => new MissionBayAgentToolProfileProvider(
				$c->get(AgentToolProfileResolver::class),
				$c->get(IAgentComponentPresetMaterializer::class),
				$c->get(AgentCapabilityCatalogBuilder::class),
				$c->get(AgentToolDefinitionSemantics::class),
				$c->get(AgentToolContractValidationService::class),
				$c->get(AgentActionFingerprint::class),
				$c->get(AgentMutationCommitGuardService::class),
				$c->get(IEventManager::class),
				$c->get(AgentBatchExecutionService::class),
				$c->get(AgentBatchResultService::class)
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
			->set(IRetrievalSearchService::class, fn($c) => new RetrievalSearchService(
				$c->get(IAgentComponentPresetRepository::class),
				$c->get(IAgentComponentPresetMaterializer::class),
				$c->get(AgentComponentPresetToolTestService::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentStagePipelineResolver::class, fn($c) => new AgentStagePipelineResolver(
				$c->get(IComponentResolver::class),
				self::DEFAULT_AGENT_STAGE_IDS
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(AgentToolOrchestrator::class, fn($c) => new AgentToolOrchestrator(
				$c->get(ILogger::class),
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

			->set(MissionBayAiUsageLogListener::class, fn($c) => new MissionBayAiUsageLogListener(
				$c->get(IDatabase::class),
				$c->get(ILogger::class),
				$c->get(IRequest::class)
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
				'stageName' => 'capability-selection'
			]
		));

		$this->registerAgentStageDefinition(new ComponentDefinition(
			id: 'ai-capability-selection',
			interfaceName: IAgentStage::class,
			implementationName: AgentAiCapabilitySelectionStage::getName(),
			arguments: [
				'id' => 'ai-capability-selection',
				'stageName' => 'ai-capability-selection'
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
				'policyIds' => ['mutation-approval-actions', 'allow-all-actions']
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
				'stageName' => 'semantic-verification'
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
