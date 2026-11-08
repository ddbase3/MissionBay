<?php declare(strict_types=1);

namespace MissionBay\Test\Display;

use AssistantFoundation\Api\IAgentConfigFormService;
use AssistantFoundation\Api\IAgentConfigurationService;
use AssistantFoundation\Api\IAgentExecutionService;
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

final class AgentAdminFileDraftTest extends TestCase {

	private function display(IAgentConfigurationService $agents, ?IRequest $request = null, ?IAgentConfigFormService $form = null): AgentAdminDisplay {
		$classMap = $this->createMock(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn([]);
		return new AgentAdminDisplay(
			$request ?? $this->createMock(IRequest::class),
			$this->createMock(IMvcView::class),
			$this->createMock(IAssetResolver::class),
			$agents,
			$this->createMock(ILinkTargetService::class),
			$classMap,
			$form ?? $this->createMock(IAgentConfigFormService::class),
			$this->createMock(IAgentExecutionService::class),
			$this->createMock(IAuthorizationService::class),
			$this->createMock(ITranslation::class)
		);
	}

	public function testCreatePersistedDraftHasImmutableCanonicalUuidAndRemainsDisabled(): void {
		$persistedId = '';
		$persistedSettings = [];
		$agents = $this->createMock(IAgentConfigurationService::class);
		$agents->method('hasAgent')->willReturn(false);
		$agents->method('isFixed')->willReturn(false);
		$agents->expects(self::once())->method('saveAgent')->willReturnCallback(
			static function(string $id, array $settings) use (&$persistedId, &$persistedSettings): void {
				$persistedId = $id;
				$persistedSettings = $settings;
			}
		);
		$form = $this->createMock(IAgentConfigFormService::class);
		// A runtime default must never accidentally activate an unsaved draft.
		$form->method('getDefaultSettings')->willReturn(['enabled' => true, 'label' => 'default label', 'chatmodel' => '']);
		$form->method('settingsToViewValues')->willReturn([]);

		$response = (new ReflectionMethod(AgentAdminDisplay::class, 'buildCreateResponse'))->invoke($this->display($agents, null, $form));
		self::assertTrue($response['ok']);
		self::assertMatchesRegularExpression(
			'/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',
			$persistedId
		);
		self::assertSame($persistedId, $response['record']['agent_id']);
		self::assertSame($persistedId, $response['record']['old_id']);
		self::assertSame($persistedId, $response['record']['agent_config_name']);
		self::assertSame('agent', $response['record']['agent_config_group']);
		self::assertFalse($persistedSettings['enabled']);
		self::assertSame('', $persistedSettings['label']);
		self::assertTrue($response['record']['is_new_draft']);
	}

	public function testDirectSaveCannotCreateAnAgentWithClientChosenIdentity(): void {
		$agents = $this->createMock(IAgentConfigurationService::class);
		$agents->method('hasAgent')->willReturn(false);
		$agents->expects(self::never())->method('saveAgent');
		$request = $this->createMock(IRequest::class);
		$request->method('request')->willReturnCallback(
			static fn(string $key, mixed $default = null): mixed => match ($key) {
				'old_id' => '',
				'agent_id' => 'client-selected',
				default => $default
			}
		);
		$response = (new ReflectionMethod(AgentAdminDisplay::class, 'buildSaveResponse'))->invoke($this->display($agents, $request));
		self::assertFalse($response['ok']);
		self::assertSame('save', $response['mode']);
	}
}
