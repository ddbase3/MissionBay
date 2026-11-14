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

namespace Base3\MissionBay\Test\Display;

use Base3\AssistantFoundation\Api\IAgentConfigFormService;
use Base3\AssistantFoundation\Api\IAgentConfigurationService;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\AssistantFoundation\Api\IAgentRuntimeRegistry;
use Base3\CoreFoundation\Api\IAssetResolver;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\LinkTarget\Api\ILinkTargetService;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\MissionBay\Display\AgentAdminDisplay;
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
			$this->createMock(IAgentRuntimeRegistry::class),
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
