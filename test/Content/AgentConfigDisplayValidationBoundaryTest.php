<?php declare(strict_types=1);

namespace MissionBay\Test\Content;

use AssistantFoundation\Api\IAgentConfigFormService;
use Base3\Api\IClassMap;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Content\AgentConfigDisplay;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AgentConfigDisplayValidationBoundaryTest extends TestCase {

	private function display(array $requestValues = [], array $storedSettings = []): AgentConfigDisplay {
		$request = $this->createStub(IRequest::class);
		$request->method('request')->willReturnCallback(static fn(string $name, mixed $default = null): mixed => $requestValues[$name] ?? $default);
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn($storedSettings);
		$view = $this->createStub(IMvcView::class);
		$view->method('loadTemplate')->willReturn('<form>configured</form>');
		$links = $this->createStub(ILinkTargetService::class);
		$links->method('getLink')->willReturn('/agent-config/save');
		$service = $this->createStub(IAgentConfigFormService::class);
		$service->method('getDefaultSettings')->willReturn([]);
		$service->method('normalizeSettings')->willReturn([]);
		$service->method('settingsToViewValues')->willReturn([]);
		$service->method('getPostedViewValues')->willReturn([]);
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn([]);
		return new AgentConfigDisplay($view, $request, $settings, $links, $classMap, $service);
	}

	private function invoke(AgentConfigDisplay $display, string $method, array $args = []): mixed {
		return (new ReflectionMethod(AgentConfigDisplay::class, $method))->invokeArgs($display, $args);
	}

	public function testUnknownOutputFormatDoesNotRender(): void {
		$this->assertSame('', $this->display()->getOutput('xml'));
	}

	public function testDisplayNameAndHelpDescribeExistingOwner(): void {
		$this->assertSame('agentconfigdisplay', AgentConfigDisplay::getName());
		$this->assertStringContainsString('ISettingsStore', $this->display()->getHelp());
	}

	public function testUnknownJsonActionIsRejected(): void {
		$result = json_decode($this->display()->getOutput('json'), true);
		$this->assertSame('error', $result['status']);
		$this->assertFalse($result['success']);
		$this->assertSame('Unknown action.', $result['messages'][0]['text']);
	}

	public function testJsonSaveRequiresMatchingConfigurationIdentity(): void {
		$result = json_decode($this->display(['action' => 'save', 'agent_config_name' => 'wrong'])->getOutput('json'), true);
		$this->assertSame('error', $result['status']);
		$this->assertSame('Configuration identity does not match.', $result['messages'][0]['text']);
	}

	public function testUnexpectedGroupCannotAuthorizeSave(): void {
		$display = $this->display(['agent_config_action' => 'save', 'agent_config_group' => 'other', 'agent_config_name' => 'default']);
		$context = $this->invoke($display, 'getContext', [true]);
		$this->assertFalse($this->invoke($display, 'isSaveRequest', [$context]));
	}

	public function testMatchingIdentityIsAcceptedForSaveBoundary(): void {
		$display = $this->display(['agent_config_action' => 'save', 'agent_config_group' => 'agent', 'agent_config_name' => 'example']);
		$display->setData(['name' => 'example', 'save_url' => '/test']);
		$context = $this->invoke($display, 'getContext', [true]);
		$this->assertTrue($this->invoke($display, 'isSaveRequest', [$context]));
	}

	public function testNoNameFallsBackToDefaultContext(): void {
		$context = $this->invoke($this->display(), 'getContext', [false]);
		$this->assertSame('agent', $context['group']);
		$this->assertSame('default', $context['name']);
		$this->assertSame('standalone', $context['mode']);
		$this->assertTrue($context['render_form']);
	}

	public function testRequestContextIsUsedOnlyWhenExplicitlyAllowed(): void {
		$display = $this->display(['agent_config_name' => 'user-target']);
		$this->assertSame('default', $this->invoke($display, 'getContext', [false])['name']);
		$this->assertSame('user-target', $this->invoke($display, 'getContext', [true])['name']);
	}

	public function testInvalidContextModesAreNormalized(): void {
		$display = $this->display();
		$display->setData(['mode' => 'unsupported', 'save_mode' => 'unsupported', 'save_url' => '/test']);
		$context = $this->invoke($display, 'getContext', [false]);
		$this->assertSame('standalone', $context['mode']);
		$this->assertSame('ajax', $context['save_mode']);
	}

	public function testEmbeddedContextDefaultsToNoForm(): void {
		$display = $this->display();
		$display->setData(['mode' => 'embedded', 'save_url' => '/test']);
		$this->assertFalse($this->invoke($display, 'getContext', [false])['render_form']);
	}

	public function testExplicitFormVisibilityOverridesEmbeddedMode(): void {
		$display = $this->display();
		$display->setData(['mode' => 'embedded', 'render_form' => 'yes', 'save_url' => '/test']);
		$this->assertTrue($this->invoke($display, 'getContext', [false])['render_form']);
	}

	public function testContextIdIsStableAndBoundToName(): void {
		$display = $this->display();
		$display->setData(['name' => 'first']);
		$id = $this->invoke($display, 'getContext', [false])['form_id'];
		$this->assertSame($id, $this->invoke($display, 'getContext', [false])['form_id']);
		$display->setData(['name' => 'second']);
		$this->assertNotSame($id, $this->invoke($display, 'getContext', [false])['form_id']);
	}

	public function testExplicitSaveUrlTakesPrecedence(): void {
		$display = $this->display();
		$display->setData(['save_url' => ' /configured ']);
		$this->assertSame('/configured', $this->invoke($display, 'getSaveUrl'));
	}

	public function testSaveUrlUsesExistingLinkResolverByDefault(): void {
		$this->assertSame('/agent-config/save', $this->invoke($this->display(), 'getSaveUrl'));
	}

	public function testHtmlOutputDelegatesToConfiguredTemplate(): void {
		$this->assertSame('<form>configured</form>', $this->display()->getOutput(' HTML '));
	}

	public function testTextNormalizationPreservesNewlines(): void {
		$this->assertSame("a\nb\nc", $this->invoke($this->display(), 'normalizeTextBlock', ["a\r\nb\rc"]));
	}

	public function testLabelCollapsesWhitespace(): void {
		$this->assertSame('Hello Agent', $this->invoke($this->display(), 'normalizeLabel', [" Hello \t Agent  "]));
	}

	public function testTechnicalKeyCannotCarryPunctuation(): void {
		$this->assertSame('adminconfig', $this->invoke($this->display(), 'normalizeTechnicalKey', [' AdMin / CONFIG ']));
	}

	public function testBooleanNormalizationMatchesStoredContract(): void {
		$display = $this->display();
		$this->assertTrue($this->invoke($display, 'toBool', ['on']));
		$this->assertTrue($this->invoke($display, 'toBool', [1]));
		$this->assertFalse($this->invoke($display, 'toBool', [2]));
		$this->assertFalse($this->invoke($display, 'toBool', ['false']));
	}

	public function testPolicyFieldsCanUseDirectSchemaProperties(): void {
		$schema = ['properties' => ['count' => ['type' => 'integer']], 'required' => ['count']];
		$this->assertSame($schema['properties'], $this->invoke($this->display(), 'getPolicySchemaProperties', [$schema]));
		$this->assertSame(['count'], $this->invoke($this->display(), 'getPolicySchemaRequired', [$schema]));
	}

	public function testPolicyFieldsCanUseNestedDataSchema(): void {
		$schema = ['data' => ['properties' => ['voice' => ['type' => 'string']], 'required' => ['voice']]];
		$this->assertSame($schema['data']['properties'], $this->invoke($this->display(), 'getPolicySchemaProperties', [$schema]));
		$this->assertSame(['voice'], $this->invoke($this->display(), 'getPolicySchemaRequired', [$schema]));
	}

	public function testMissingPolicySchemaUsesNoArbitraryFields(): void {
		$this->assertSame([], $this->invoke($this->display(), 'getPolicySchemaProperties', [[]]));
		$this->assertSame([], $this->invoke($this->display(), 'getPolicySchemaRequired', [[]]));
	}

	public function testMalformedPolicyJsonReturnsNoValueAndReportsError(): void {
		$errors = [];
		$result = $this->invoke($this->display(), 'decodePolicyJsonField', ['{broken', 'data', &$errors]);
		$this->assertNull($result);
		$this->assertStringContainsString('must be valid JSON', $errors[0]);
	}

	public function testValidPolicyJsonIsDecodedAsStructuredValues(): void {
		$errors = [];
		$result = $this->invoke($this->display(), 'decodePolicyJsonField', ['{"items":[1,2]}', 'data', &$errors]);
		$this->assertSame(['items' => [1, 2]], $result);
		$this->assertSame([], $errors);
	}

	public function testBase64PolicyRejectsMalformedEncoding(): void {
		$errors = [];
		$result = $this->invoke($this->display(['policy_data_b64' => '!invalid!']), 'decodePostedBase64Field', ['policy_data_b64', 'Policy', &$errors]);
		$this->assertSame('', $result);
		$this->assertStringContainsString('could not be decoded', $errors[0]);
	}

	public function testBase64PolicyDecodesAndTrimsValidValue(): void {
		$errors = [];
		$result = $this->invoke($this->display(['policy_data_b64' => base64_encode(' {"safe":true} ')]), 'decodePostedBase64Field', ['policy_data_b64', 'Policy', &$errors]);
		$this->assertSame('{"safe":true}', $result);
		$this->assertSame([], $errors);
	}

	public function testEmptyPolicyDataDoesNotInventValues(): void {
		$errors = [];
		$result = $this->invoke($this->display(), 'normalizePostedPolicyData', ['', &$errors]);
		$this->assertSame([], $result);
		$this->assertSame([], $errors);
	}

	public function testUntrustedPolicyDataIsRestrictedToDeclaredSchemaKeys(): void {
		$errors = [];
		$result = $this->invoke($this->display(['policy_data_json' => '{"admin":true}']), 'normalizePostedPolicyData', ['', &$errors]);
		$this->assertSame([], $result);
		$this->assertSame([], $errors);
	}

	public function testInvalidPolicyJsonIsReportedWithoutSaving(): void {
		$errors = [];
		$this->invoke($this->display(['policy_data_json' => '{broken']), 'normalizePostedPolicyData', ['', &$errors]);
		$this->assertStringContainsString('must be valid JSON', implode('; ', $errors));
	}
}
