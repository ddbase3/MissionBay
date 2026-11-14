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

namespace Base3\MissionBay\Test\SettingsTab;

use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationDecision;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationRequest;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Usermanager\Permission;
use Base3\MissionBay\Feature\VectorSearchConfigFeature;
use PHPUnit\Framework\TestCase;
use Base3\UiFoundation\Settings\AbstractFeatureSettingsTab;
use Base3\MissionBay\SettingsTab\AgentToolsSettingsTab;
use Base3\MissionBay\SettingsTab\AgentadminSettingsTab;
use Base3\MissionBay\SettingsTab\AgentcomponentpresetadminSettingsTab;
use Base3\MissionBay\SettingsTab\AgentcomponentpresettestadminSettingsTab;
use Base3\MissionBay\SettingsTab\AgentcompositionadminSettingsTab;
use Base3\MissionBay\SettingsTab\AgentcontextprofileadminSettingsTab;
use Base3\MissionBay\SettingsTab\AgentinfotopicprovidertestadminSettingsTab;
use Base3\MissionBay\SettingsTab\AgentmemoryprofileadminSettingsTab;
use Base3\MissionBay\SettingsTab\AgentorchestratorprofileadminSettingsTab;
use Base3\MissionBay\SettingsTab\AgentsSettingsTab;
use Base3\MissionBay\SettingsTab\AgenttoollogadminSettingsTab;
use Base3\MissionBay\SettingsTab\AiUsageModelsSettingsTab;
use Base3\MissionBay\SettingsTab\AiUsageOperationsSettingsTab;
use Base3\MissionBay\SettingsTab\AiUsageOverviewSettingsTab;
use Base3\MissionBay\SettingsTab\AiUsageRequestsSettingsTab;
use Base3\MissionBay\SettingsTab\AiUsageSettingsTab;
use Base3\MissionBay\SettingsTab\AiUsageTokensSettingsTab;
use Base3\MissionBay\SettingsTab\AiUsageUsersSettingsTab;
use Base3\MissionBay\SettingsTab\ConnectionconfigSettingsTab;
use Base3\MissionBay\SettingsTab\EmbeddingSettingsTab;
use Base3\MissionBay\SettingsTab\EmbeddingconfigSettingsTab;
use Base3\MissionBay\SettingsTab\EmbeddingorchestratorconfigadminSettingsTab;
use Base3\MissionBay\SettingsTab\ImageconfigSettingsTab;
use Base3\MissionBay\SettingsTab\KnowledgeagentmemoryadminSettingsTab;
use Base3\MissionBay\SettingsTab\LlmconfigSettingsTab;
use Base3\MissionBay\SettingsTab\ParserserviceconfigSettingsTab;
use Base3\MissionBay\SettingsTab\ProviderSettingsTab;
use Base3\MissionBay\SettingsTab\RetrievalcollectionadminSettingsTab;
use Base3\MissionBay\SettingsTab\RetrievalsearchadminSettingsTab;
use Base3\MissionBay\SettingsTab\RetrievalvectorpointsadminSettingsTab;
use Base3\MissionBay\SettingsTab\SearchconfigSettingsTab;
use Base3\MissionBay\SettingsTab\SpeechtotextconfigSettingsTab;
use Base3\MissionBay\SettingsTab\TexttospeechconfigSettingsTab;
use Base3\MissionBay\SettingsTab\ToolprofileadminSettingsTab;
use Base3\MissionBay\SettingsTab\UserprefdefadminSettingsTab;
use Base3\MissionBay\SettingsTab\VectorsearchconfigSettingsTab;
use Base3\MissionBay\SettingsTab\VectorstoreconfigSettingsTab;

final class MissionBaySettingsTabCatalogTest extends TestCase {

	/** @var array<class-string,array{order:int,parent:?string,display:?string,feature:bool,data:mixed}> */
	private const TABS = [
		AgentToolsSettingsTab::class => ['order' => 60, 'parent' => null, 'display' => null, 'feature' => false, 'data' => null],
		AgentadminSettingsTab::class => ['order' => 10, 'parent' => 'agentssettingstab', 'display' => 'agentadmindisplay', 'feature' => true, 'data' => null],
		AgentcomponentpresetadminSettingsTab::class => ['order' => 70, 'parent' => 'agentssettingstab', 'display' => 'agentcomponentpresetadmindisplay', 'feature' => true, 'data' => null],
		AgentcomponentpresettestadminSettingsTab::class => ['order' => 20, 'parent' => 'agenttoolssettingstab', 'display' => 'agentcomponentpresettestadmindisplay', 'feature' => true, 'data' => null],
		AgentcompositionadminSettingsTab::class => ['order' => 20, 'parent' => 'agentssettingstab', 'display' => 'agentcompositionadmindisplay', 'feature' => true, 'data' => null],
		AgentcontextprofileadminSettingsTab::class => ['order' => 60, 'parent' => 'agentssettingstab', 'display' => 'agentcontextprofileadmindisplay', 'feature' => true, 'data' => null],
		AgentinfotopicprovidertestadminSettingsTab::class => ['order' => 30, 'parent' => 'agenttoolssettingstab', 'display' => 'agentinfotopicprovidertestadmindisplay', 'feature' => true, 'data' => null],
		AgentmemoryprofileadminSettingsTab::class => ['order' => 50, 'parent' => 'agentssettingstab', 'display' => 'agentmemoryprofileadmindisplay', 'feature' => true, 'data' => null],
		AgentorchestratorprofileadminSettingsTab::class => ['order' => 30, 'parent' => 'agentssettingstab', 'display' => 'agentorchestratorprofileadmindisplay', 'feature' => true, 'data' => null],
		AgentsSettingsTab::class => ['order' => 50, 'parent' => null, 'display' => null, 'feature' => false, 'data' => null],
		AgenttoollogadminSettingsTab::class => ['order' => 10, 'parent' => 'agenttoolssettingstab', 'display' => 'agenttoollogadmindisplay', 'feature' => true, 'data' => null],
		AiUsageModelsSettingsTab::class => ['order' => 30, 'parent' => 'aiusagesettingstab', 'display' => 'generalreportdisplay', 'feature' => true, 'data' => 'missionbay_ai_usage_models'],
		AiUsageOperationsSettingsTab::class => ['order' => 40, 'parent' => 'aiusagesettingstab', 'display' => 'generalreportdisplay', 'feature' => true, 'data' => 'missionbay_ai_usage_operations'],
		AiUsageOverviewSettingsTab::class => ['order' => 10, 'parent' => 'aiusagesettingstab', 'display' => 'compositedisplay', 'feature' => true, 'data' => null],
		AiUsageRequestsSettingsTab::class => ['order' => 60, 'parent' => 'aiusagesettingstab', 'display' => 'generalreportdisplay', 'feature' => true, 'data' => 'missionbay_ai_usage_requests'],
		AiUsageSettingsTab::class => ['order' => 40, 'parent' => null, 'display' => null, 'feature' => false, 'data' => null],
		AiUsageTokensSettingsTab::class => ['order' => 20, 'parent' => 'aiusagesettingstab', 'display' => 'generalreportdisplay', 'feature' => true, 'data' => 'missionbay_ai_usage_tokens_timeline'],
		AiUsageUsersSettingsTab::class => ['order' => 50, 'parent' => 'aiusagesettingstab', 'display' => 'generalreportdisplay', 'feature' => true, 'data' => 'missionbay_ai_usage_users'],
		ConnectionconfigSettingsTab::class => ['order' => 10, 'parent' => 'providersettingstab', 'display' => 'connectionconfigdisplay', 'feature' => true, 'data' => null],
		EmbeddingSettingsTab::class => ['order' => 70, 'parent' => null, 'display' => null, 'feature' => false, 'data' => null],
		EmbeddingconfigSettingsTab::class => ['order' => 30, 'parent' => 'providersettingstab', 'display' => 'embeddingconfigdisplay', 'feature' => true, 'data' => null],
		EmbeddingorchestratorconfigadminSettingsTab::class => ['order' => 10, 'parent' => 'embeddingsettingstab', 'display' => 'embeddingorchestratorconfigadmindisplay', 'feature' => true, 'data' => null],
		ImageconfigSettingsTab::class => ['order' => 60, 'parent' => 'providersettingstab', 'display' => 'imageconfigdisplay', 'feature' => true, 'data' => null],
		KnowledgeagentmemoryadminSettingsTab::class => ['order' => 40, 'parent' => 'agenttoolssettingstab', 'display' => 'knowledgeagentmemoryadmindisplay', 'feature' => true, 'data' => null],
		LlmconfigSettingsTab::class => ['order' => 20, 'parent' => 'providersettingstab', 'display' => 'llmconfigdisplay', 'feature' => true, 'data' => null],
		ParserserviceconfigSettingsTab::class => ['order' => 80, 'parent' => 'providersettingstab', 'display' => 'parserserviceconfigdisplay', 'feature' => true, 'data' => null],
		ProviderSettingsTab::class => ['order' => 30, 'parent' => null, 'display' => null, 'feature' => false, 'data' => null],
		RetrievalcollectionadminSettingsTab::class => ['order' => 20, 'parent' => 'embeddingsettingstab', 'display' => 'retrievalcollectionadmindisplay', 'feature' => true, 'data' => null],
		RetrievalsearchadminSettingsTab::class => ['order' => 40, 'parent' => 'embeddingsettingstab', 'display' => 'retrievalsearchadmindisplay', 'feature' => true, 'data' => null],
		RetrievalvectorpointsadminSettingsTab::class => ['order' => 30, 'parent' => 'embeddingsettingstab', 'display' => 'retrievalvectorpointsadmindisplay', 'feature' => true, 'data' => null],
		SearchconfigSettingsTab::class => ['order' => 70, 'parent' => 'providersettingstab', 'display' => 'searchconfigdisplay', 'feature' => true, 'data' => null],
		SpeechtotextconfigSettingsTab::class => ['order' => 40, 'parent' => 'providersettingstab', 'display' => 'speechtotextconfigdisplay', 'feature' => true, 'data' => null],
		TexttospeechconfigSettingsTab::class => ['order' => 50, 'parent' => 'providersettingstab', 'display' => 'texttospeechconfigdisplay', 'feature' => true, 'data' => null],
		ToolprofileadminSettingsTab::class => ['order' => 40, 'parent' => 'agentssettingstab', 'display' => 'toolprofileadmindisplay', 'feature' => true, 'data' => null],
		UserprefdefadminSettingsTab::class => ['order' => 50, 'parent' => 'agenttoolssettingstab', 'display' => 'userprefdefadmindisplay', 'feature' => true, 'data' => null],
		VectorsearchconfigSettingsTab::class => ['order' => 110, 'parent' => 'providersettingstab', 'display' => 'vectorsearchconfigdisplay', 'feature' => false, 'data' => null],
		VectorstoreconfigSettingsTab::class => ['order' => 90, 'parent' => 'providersettingstab', 'display' => 'vectorstoreconfigdisplay', 'feature' => true, 'data' => null],
	];

	private function translation(): ITranslation {
		$translation = $this->createStub(ITranslation::class);
		$translation->method('translate')->willReturn('Translated value');
		return $translation;
	}

	private function authority(AuthorizationDecision $decision): IAuthorizationService {
		$authorization = $this->createStub(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('native-host');
		$authorization->method('decide')->willReturn($decision);
		return $authorization;
	}

	public function testEveryDeclaredTabHasStableIdentityAndNavigationMetadata(): void {
		$translation = $this->translation();
		$authorization = $this->authority(AuthorizationDecision::allow());
		$this->assertCount(37, self::TABS);
		foreach (self::TABS as $class => $expected) {
			$tab = $expected['feature'] || $class === VectorsearchconfigSettingsTab::class
				? new $class($translation, $authorization)
				: new $class($translation);
			$this->assertSame(strtolower((new \ReflectionClass($class))->getShortName()), $tab::getName(), $class);
			$this->assertSame($expected['order'], $tab->getOrder(), $class);
			$this->assertSame($expected['parent'], $tab->getParentTabName(), $class);
			$this->assertSame($expected['display'], $tab->getDisplayName(), $class);
			$this->assertSame('Translated value', $tab->getLabel(), $class);
			$this->assertSame('Translated value', $tab->getDescription(), $class);
		}
	}

	public function testEveryChildReferencesAnExistingRootTab(): void {
		$rootNames = [];
		foreach (self::TABS as $class => $metadata) {
			if ($metadata['parent'] === null) {
				$rootNames[] = $class::getName();
				$this->assertSame(null, $metadata['display']);
			}
		}
		$this->assertCount(5, $rootNames);
		foreach (self::TABS as $class => $metadata) {
			if ($metadata['parent'] !== null) {
				$this->assertTrue(in_array($metadata['parent'], $rootNames, true), $class);
				$this->assertTrue($metadata['display'] !== '', $class);
			}
		}
	}

	public function testReportingTabsExposeTheExpectedReportIdentifiers(): void {
		$translation = $this->translation();
		$authorization = $this->authority(AuthorizationDecision::deny());
		foreach (self::TABS as $class => $metadata) {
			if ($metadata['data'] === null) {
				continue;
			}
			$tab = new $class($translation, $authorization);
			$this->assertSame($metadata['data'], $tab->getDisplayData(), $class);
		}
		$overview = new AiUsageOverviewSettingsTab($translation, $authorization);
		$data = $overview->getDisplayData();
		$this->assertSame(2, $data['columns']);
		$this->assertSame(['metrics', 'tokens', 'operations', 'models'], array_column($data['items'], 'id'));
		$this->assertSame('missionbay_ai_usage_models', $data['items'][3]['data']);
		$this->assertSame('Translated value', $data['items'][3]['title']);
	}

	public function testFeatureTabsExposeExactlyTheirOwnFeaturePermission(): void {
		$translation = $this->translation();
		$authorization = $this->authority(AuthorizationDecision::allow());
		foreach (self::TABS as $class => $metadata) {
			if (!$metadata['feature']) {
				continue;
			}
			$tab = new $class($translation, $authorization);
			$this->assertTrue($tab instanceof AbstractFeatureSettingsTab);
			$permissions = $tab->getPermissions();
			$this->assertCount(1, $permissions, $class);
			$this->assertSame('feature', $permissions[0]->scope, $class);
			$this->assertSame($class::getName(), $permissions[0]->permission, $class);
			$this->assertSame(['group' => 'administration'], $tab->getDefaultAudience());
		}
	}

	public function testFeatureTabsRequireNativeAllowAndFailClosed(): void {
		$translation = $this->translation();
		foreach ([
			AuthorizationDecision::allow(),
			AuthorizationDecision::deny(),
			AuthorizationDecision::indeterminate()
		] as $decision) {
			foreach (self::TABS as $class => $metadata) {
				if (!$metadata['feature']) {
					continue;
				}
				$authorization = $this->createStub(IAuthorizationService::class);
				$authorization->method('getDefaultAuthority')->willReturn('native-host');
				$authorization->method('decide')->willReturnCallback(function (AuthorizationRequest $request) use ($class, $decision): AuthorizationDecision {
					$this->assertSame('native-host', $request->authority);
					$this->assertSame('feature', $request->permission->scope);
					$this->assertSame($class::getName(), $request->permission->permission);
					return $decision;
				});
				$tab = new $class($translation, $authorization);
				$this->assertSame($decision->isAllowed(), $tab->isVisible(), $class);
			}
		}
	}

	public function testAuthorizationExceptionsNeverShowProtectedTabs(): void {
		$translation = $this->translation();
		$authorization = $this->createStub(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('native-host');
		$authorization->method('decide')->willThrowException(new \RuntimeException('native provider unavailable'));
		foreach (self::TABS as $class => $metadata) {
			if (!$metadata['feature']) {
				continue;
			}
			$this->assertFalse((new $class($translation, $authorization))->isVisible(), $class);
		}
	}

	public function testRootTabsAreNotSubjectToFeatureAuthorization(): void {
		$translation = $this->translation();
		foreach (self::TABS as $class => $metadata) {
			if ($metadata['parent'] !== null || $metadata['feature']) {
				continue;
			}
			$this->assertTrue((new $class($translation))->isVisible(), $class);
		}
	}

	public function testVectorSearchSettingsUsesItsDedicatedFeaturePermission(): void {
		$translation = $this->translation();
		foreach ([AuthorizationDecision::allow(), AuthorizationDecision::deny(), AuthorizationDecision::indeterminate()] as $decision) {
			$authorization = $this->createStub(IAuthorizationService::class);
			$authorization->method('getDefaultAuthority')->willReturn('native-host');
			$authorization->method('decide')->willReturnCallback(function (AuthorizationRequest $request) use ($decision): AuthorizationDecision {
				$this->assertSame('native-host', $request->authority);
				$this->assertSame('feature', $request->permission->scope);
				$this->assertSame(VectorSearchConfigFeature::getName(), $request->permission->permission);
				return $decision;
			});
			$this->assertSame($decision->isAllowed(), (new VectorsearchconfigSettingsTab($translation, $authorization))->isVisible());
		}
	}

	public function testVectorSearchSettingsHidesWhenNativeAuthorizationThrows(): void {
		$authorization = $this->createStub(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('native-host');
		$authorization->method('decide')->willThrowException(new \RuntimeException('unavailable'));
		$this->assertFalse((new VectorsearchconfigSettingsTab($this->translation(), $authorization))->isVisible());
	}
}
