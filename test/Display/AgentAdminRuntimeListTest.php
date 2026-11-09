<?php declare(strict_types=1);

namespace MissionBay\Test\Display;

use AssistantFoundation\Api\IAgentConfigFormService;
use AssistantFoundation\Api\IAgentConfigurationService;
use AssistantFoundation\Api\IAgentExecutionService;
use AssistantFoundation\Api\IAgentRuntimeConfigFormService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Translation\Api\ITranslation;
use MissionBay\Display\AgentAdminDisplay;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AgentAdminRuntimeListTest extends TestCase {

	public function testMissingRuntimeDoesNotHideStoredAgentsOrResolveTheirRuntimeForms(): void {
		$agents = $this->createMock(IAgentConfigurationService::class);
		$agents->method('getAgents')->willReturn([
			'agent-neuron' => [
				'label' => 'Imported agent',
				'enabled' => false,
				'agent_runtime' => 'neuronai',
				'user_prompt' => 'Imported prompt',
				'policy' => ['policy' => 'manualonlyjobpolicy', 'data' => []]
			],
			'agent-legacy' => [
				'label' => 'Legacy agent',
				'enabled' => true,
				'policy' => ['policy' => 'manualonlyjobpolicy', 'data' => []]
			],
			'agent-local' => [
				'label' => 'Local agent',
				'enabled' => true,
				'agent_runtime' => 'missionbay',
				'chatmodel' => 'mistral_small',
				'policy' => ['policy' => 'manualonlyjobpolicy', 'data' => []]
			]
		]);
		$agents->method('isFixed')->willReturn(false);

		$runtimeForm = $this->createMock(IAgentRuntimeConfigFormService::class);
		$runtimeForm->expects(self::once())->method('getConfigurationSummary')
			->willReturn(['provider' => 'MissionBay', 'model' => 'mistral_small']);

		$registry = $this->createMock(IAgentRuntimeRegistry::class);
		$registry->method('hasRuntime')->willReturnCallback(
			static fn(string $id): bool => $id === 'missionbay'
		);
		$registry->expects(self::once())->method('getConfigFormService')
			->with('missionbay')->willReturn($runtimeForm);

		$agentForm = $this->createMock(IAgentConfigFormService::class);
		$agentForm->expects(self::never())->method('settingsToViewValues');
		$agentForm->expects(self::never())->method('normalizeSettings');

		$classMap = $this->createMock(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn([]);

		$display = new AgentAdminDisplay(
			$this->createMock(IRequest::class),
			$this->createMock(IMvcView::class),
			$this->createMock(IAssetResolver::class),
			$agents,
			$this->createMock(ILinkTargetService::class),
			$classMap,
			$agentForm,
			$this->createMock(IAgentExecutionService::class),
			$registry,
			$this->createMock(IAuthorizationService::class),
			$this->createMock(ITranslation::class)
		);

		$request = [
			'mode' => 'page',
			'page' => 1,
			'pageSize' => 50,
			'search' => '',
			'sort' => ['key' => 'agent_id', 'dir' => 'asc', 'type' => 'string'],
			'filters' => []
		];
		$response = (new ReflectionMethod(AgentAdminDisplay::class, 'buildPageResponse'))
			->invoke($display, $request);

		self::assertTrue($response['ok']);
		self::assertSame(3, $response['total']);
		self::assertSame(['agent-legacy', 'agent-local', 'agent-neuron'], array_column($response['data'], 'agent_id'));
		self::assertSame('', $response['data'][0]['agent_runtime']);
		self::assertSame('MissionBay', $response['data'][1]['agent_provider']);
		self::assertSame('mistral_small', $response['data'][1]['agent_model']);
		self::assertSame('neuronai', $response['data'][2]['agent_runtime']);
		self::assertSame('Imported agent', $response['data'][2]['label']);
		self::assertSame('', $response['data'][2]['agent_provider']);
		self::assertFalse($response['data'][2]['enabled']);
	}
}
