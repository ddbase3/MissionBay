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

namespace Base3\MissionBay\Test\Service;

use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentConfigFormNormalizationProbe.php';

final class AgentConfigFormNormalizationCoverageTest extends TestCase {

	private function form(): AgentConfigFormNormalizationProbe {
		return new AgentConfigFormNormalizationProbe();
	}

	public function testDefaultSettingsExposeStableAgentFields(): void {
		$defaults = $this->form()->getDefaultSettings();
		$this->assertSame('', $defaults['chatmodel']);
		$this->assertSame('standard', $defaults['orchestrator_profile']);
		$this->assertSame([], $defaults['tool_profiles']);
		$this->assertSame([], $defaults['agent_components']);
		$this->assertSame((new AgentCapabilitySourceConfig())->toArray(), $defaults['capability_sources']);
		$this->assertSame((new AgentCapabilitySelectionConfig())->toArray(), $defaults['capability_selection']);
	}

	public function testTechnicalKeysAreTrimmedLowercasedAndRestricted(): void {
		$this->assertSame('my-agent_1.2', $this->form()->technicalKey("  My-Agent_1.2! "));
	}

	public function testTechnicalKeysRejectUnsupportedCharacters(): void {
		$this->assertSame('', $this->form()->technicalKey('!? /'));
	}

	public function testTechnicalKeyListSplitsLinesAndCommas(): void {
		$this->assertSame(['one', 'two', 'three'], $this->form()->technicalKeyList(" One,Two\r\nthree "));
	}

	public function testTechnicalKeyListDeduplicatesNormalizedIds(): void {
		$this->assertSame(['a', 'b'], $this->form()->technicalKeyList(['A!', 'a', 'B', '  ', 'b']));
	}

	public function testTechnicalKeyListRejectsScalarOtherThanStringOrArray(): void {
		$this->assertSame([], $this->form()->technicalKeyList(12));
	}

	public function testStringListSplitsCommas(): void {
		$this->assertSame(['one', 'two'], $this->form()->stringList(' one, two '));
	}

	public function testStringListDeduplicatesButPreservesCase(): void {
		$this->assertSame(['One', 'one'], $this->form()->stringList([' One ', '', 'one', 'One']));
	}

	public function testStringListRejectsInvalidInputs(): void {
		$this->assertSame([], $this->form()->stringList(null));
		$this->assertSame([], $this->form()->stringList(42));
	}

	public function testBooleanTrueForms(): void {
		foreach ([true, 1, '1', 'yes', 'TRUE', ' On '] as $value) {
			$this->assertTrue($this->form()->boolean($value));
		}
	}

	public function testBooleanFalseForms(): void {
		foreach ([false, 0, 2, '0', 'false', 'off', 'no', ''] as $value) {
			$this->assertFalse($this->form()->boolean($value));
		}
	}

	public function testTextBlockNormalizesAllLineEndings(): void {
		$this->assertSame("a\nb\nc", $this->form()->textBlock("a\r\nb\rc"));
	}

	public function testDefaultCapabilitySourcesWhenInvalidJson(): void {
		$this->assertSame((new AgentCapabilitySourceConfig())->toArray(), $this->form()->capabilitySources('not json'));
	}

	public function testCapabilitySourcesFromJsonKeepOnlyDeclaredIds(): void {
		$source = $this->form()->capabilitySources('{"tools":" one, two , one","strict":false}');
		$this->assertSame(['one', 'two'], $source['tools']);
		$this->assertFalse($source['strict']);
	}

	public function testCapabilitySourcesAcceptArrayAliasKeys(): void {
		$source = $this->form()->capabilitySources(['tool_ids' => ['tool-x'], 'moduleIds' => ['module-y']]);
		$this->assertSame(['tool-x'], $source['tools']);
		$this->assertSame(['module-y'], $source['modules']);
	}

	public function testCapabilitySelectionRejectsMalformedJsonWithoutErrors(): void {
		$errors = [];
		$this->assertSame((new AgentCapabilitySelectionConfig())->toArray(), $this->form()->capabilitySelection('{', $errors));
		$this->assertSame([], $errors);
	}

	public function testCapabilitySelectionSplitsToolNameLists(): void {
		$errors = [];
		$selected = $this->form()->capabilitySelection(['include_tools' => "alpha, beta\ngamma", 'exclude_tags' => 'secret,private'], $errors);
		$this->assertSame(['alpha', 'beta', 'gamma'], $selected['include_tools']);
		$this->assertSame(['secret', 'private'], $selected['exclude_tags']);
		$this->assertSame([], $errors);
	}

	public function testCapabilitySelectionAcceptsJsonString(): void {
		$errors = [];
		$selected = $this->form()->capabilitySelection('{"strategy":"all","max_tools":2}', $errors);
		$this->assertSame('all', $selected['strategy']);
		$this->assertSame(2, $selected['max_tools']);
	}

	public function testInvalidCapabilityStrategyReturnsSafeDefaultsAndError(): void {
		$errors = [];
		$selected = $this->form()->capabilitySelection(['strategy' => 'unsupported'], $errors);
		$this->assertSame((new AgentCapabilitySelectionConfig())->toArray(), $selected);
		$this->assertCount(1, $errors);
		$this->assertStringContainsString('Unsupported capability selection strategy', $errors[0]);
	}

	public function testInvalidCapabilityBudgetReturnsSafeDefaultsAndError(): void {
		$errors = [];
		$selected = $this->form()->capabilitySelection(['max_tools' => 0], $errors);
		$this->assertSame(16, $selected['max_tools']);
		$this->assertCount(1, $errors);
	}

	public function testInvalidSelectionUnitReturnsSafeDefaultsAndError(): void {
		$errors = [];
		$selected = $this->form()->capabilitySelection(['selection_unit' => 'unknown'], $errors);
		$this->assertSame('function', $selected['selection_unit']);
		$this->assertCount(1, $errors);
	}

	public function testAttachCapabilitiesAcceptsOnlyDeclaredValues(): void {
		$this->assertSame(['tool', 'memory', 'context'], $this->form()->attachCapabilities(' TOOL,memory,invalid, context,tool'));
	}

	public function testAttachCapabilitiesRejectsNonLists(): void {
		$this->assertSame([], $this->form()->attachCapabilities(42));
	}

	public function testAttachAsHonorsExplicitFlagsAndDeduplicates(): void {
		$this->assertSame(['tool', 'memory'], $this->form()->attachAs(['attach_as' => [' TOOL ', 'bad', 'tool'], 'attach_memory' => 'yes']));
	}

	public function testAttachAsIgnoresUnknownCapabilities(): void {
		$this->assertSame([], $this->form()->attachAs(['attach_as' => ['bad']]));
	}

	public function testMemoryConfigEnforcesEnabledAndOptionalPriority(): void {
		$config = $this->form()->memoryConfig(['memory_config' => ['enabled' => ['mode' => 'fixed', 'value' => false]]], '21');
		$this->assertSame(['mode' => 'fixed', 'value' => true], $config['enabled']);
		$this->assertSame(['mode' => 'fixed', 'value' => 21], $config['priority']);
	}

	public function testMemoryConfigPreservesOtherFieldsWhenNoOrder(): void {
		$config = $this->form()->memoryConfig(['memory_config' => ['namespace' => 'private']], '');
		$this->assertSame('private', $config['namespace']);
		$this->assertArrayNotHasKey('priority', $config);
	}

	public function testToolConfigAcceptsMetadataAndTags(): void {
		$config = $this->form()->toolConfig(['namespace' => ' safe ', 'label' => ' My Tool ', 'description' => 'Example', 'tags' => 'read, public,read', 'priority' => ' 5 ']);
		$this->assertSame(['mode' => 'fixed', 'value' => true], $config['enabled']);
		$this->assertSame(['mode' => 'fixed', 'value' => 'safe'], $config['namespace']);
		$this->assertSame(['mode' => 'fixed', 'value' => ['read', 'public']], $config['tags']);
		$this->assertSame(['mode' => 'fixed', 'value' => 5], $config['priority']);
	}

	public function testToolConfigRemovesEmptyOptionalFields(): void {
		$config = $this->form()->toolConfig(['namespace' => ' ', 'tags' => '', 'priority' => ' ']);
		$this->assertArrayNotHasKey('namespace', $config);
		$this->assertArrayNotHasKey('tags', $config);
		$this->assertArrayNotHasKey('priority', $config);
	}

	public function testToolConfigCanReadExistingFixedSettings(): void {
		$config = $this->form()->toolConfig(['tool_config' => ['category' => ['mode' => 'fixed', 'value' => 'docs'], 'tags' => ['mode' => 'fixed', 'value' => ['docs']]]]);
		$this->assertSame(['mode' => 'fixed', 'value' => 'docs'], $config['category']);
		$this->assertSame(['mode' => 'fixed', 'value' => ['docs']], $config['tags']);
	}

	public function testReadFixedIgnoresOtherModes(): void {
		$this->assertSame('default', $this->form()->readFixed(['a' => ['mode' => 'env', 'value' => 'secret']], 'a', 'default'));
	}

	public function testReadFixedReturnsConfiguredValue(): void {
		$this->assertSame(['a'], $this->form()->readFixed(['a' => ['mode' => 'fixed', 'value' => ['a']]], 'a', []));
	}

	public function testNormalizeComponentsRejectsUnsupportedInput(): void {
		$errors = [];
		$this->assertSame([], $this->form()->normalizeComponents('invalid', $errors));
		$this->assertSame([], $errors);
	}

	public function testNormalizeComponentsKeepsValidToolAndMemoryEntries(): void {
		$errors = [];
		$items = $this->form()->normalizeComponents([['preset' => 'Example-Preset', 'enabled' => 'on', 'attach_as' => ['tool', 'memory'], 'order' => '10', 'tags' => 'read,write']], $errors);
		$this->assertCount(1, $items);
		$this->assertSame('example-preset', $items[0]['preset']);
		$this->assertSame(['tool', 'memory'], $items[0]['attach_as']);
		$this->assertSame(['mode' => 'fixed', 'value' => 10], $items[0]['memory_config']['priority']);
		$this->assertSame(['mode' => 'fixed', 'value' => ['read', 'write']], $items[0]['tool_config']['tags']);
		$this->assertSame([], $errors);
	}

	public function testNormalizeComponentsDropsOnlyChatModelAttachment(): void {
		$errors = [];
		$items = $this->form()->normalizeComponents([['preset' => 'p', 'attach_as' => ['chatmodel', 'context']]], $errors);
		$this->assertCount(1, $items);
		$this->assertSame(['context'], $items[0]['attach_as']);
	}

	public function testNormalizeComponentsRejectsPresetWithOnlyChatModel(): void {
		$errors = [];
		$items = $this->form()->normalizeComponents([['preset' => 'chat', 'attach_as' => ['chatmodel']]], $errors);
		$this->assertSame([], $items);
		$this->assertCount(1, $errors);
	}

	public function testNormalizeComponentsSkipsMalformedRows(): void {
		$errors = [];
		$items = $this->form()->normalizeComponents([null, 'a', ['preset' => ''], ['preset' => 'p', 'attach_as' => ['tool']]], $errors);
		$this->assertCount(1, $items);
	}

	public function testSensitiveKeysIncludeCredentialsAndSuffixes(): void {
		foreach (['api_key', 'password', 'clientsecret', 'refresh_token', 'service_private_key', 'authorization', 'credentials'] as $key) {
			$this->assertTrue($this->form()->sensitive($key));
		}
	}

	public function testOrdinaryKeysAreNotRedacted(): void {
		foreach (['model', 'provider', 'access', 'tokenizer', 'key', ''] as $key) {
			$this->assertFalse($this->form()->sensitive($key));
		}
	}

	public function testNestedSensitiveFieldsAreRedactedWithoutChangingOtherData(): void {
		$input = ['id' => 'test', 'settings' => ['api_key' => 'secret', 'nested' => [['refresh_token' => 'private', 'model' => 'gpt']]]];
		$this->assertSame(['id' => 'test', 'settings' => ['api_key' => '***REDACTED***', 'nested' => [['refresh_token' => '***REDACTED***', 'model' => 'gpt']]]], $this->form()->redacted($input));
	}

	public function testSensitiveKeyAtRootIsRedacted(): void {
		$this->assertSame(['secret' => '***REDACTED***'], $this->form()->redacted(['secret' => ['data' => 'private']]));
	}

	public function testRedactionPreservesNonArrayScalars(): void {
		$this->assertSame('visible', $this->form()->redacted('visible'));
	}

	public function testPresetOptionFilteringKeepsOnlyRequestedCapability(): void {
		$options = [['id' => 'm', 'capabilities' => ['chatmodel']], ['id' => 't', 'capabilities' => ['tool']], ['id' => 'x']];
		$this->assertSame([['id' => 'm', 'capabilities' => ['chatmodel']]], $this->form()->matchingPresets($options, ' ChatModel '));
	}

	public function testPresetOptionFilteringReturnsOtherCapabilities(): void {
		$options = [['id' => 'm', 'capabilities' => ['chatmodel']], ['id' => 't', 'capabilities' => ['tool']], ['id' => 'x']];
		$this->assertSame([['id' => 't', 'capabilities' => ['tool']], ['id' => 'x']], $this->form()->otherPresets($options, 'chatmodel'));
	}

	public function testConfigurationSummaryUsesNormalizedModel(): void {
		$this->assertSame(['provider' => 'MissionBay', 'model' => 'model-a'], $this->form()->getConfigurationSummary(['chatmodel' => ' MODEL-A! ']));
	}

	public function testNormalizeSettingsUsesSafeDefaults(): void {
		$settings = $this->form()->normalizeSettings([]);
		$this->assertSame('standard', $settings['orchestrator_profile']);
		$this->assertSame([], $settings['agent_components']);
		$this->assertFalse($settings['expert_overrides_enabled']);
	}

	public function testNormalizeSettingsNormalizesOverridesAndComponents(): void {
		$settings = $this->form()->normalizeSettings(['chatmodel' => ' GPT ', 'tool_profiles' => ['A', 'A', 'B'], 'expert_overrides_enabled' => 'yes', 'system_prompt' => "a\r\nb", 'agent_components' => [['preset' => 'a', 'attach_tool' => true]]]);
		$this->assertSame('gpt', $settings['chatmodel']);
		$this->assertSame(['a', 'b'], $settings['tool_profiles']);
		$this->assertTrue($settings['expert_overrides_enabled']);
		$this->assertSame("a\nb", $settings['system_prompt']);
		$this->assertCount(1, $settings['agent_components']);
	}

	public function testSettingsToViewValuesUsesNormalizedFields(): void {
		$settings = $this->form()->settingsToViewValues(['chatmodel' => ' MODEL-X ']);
		$this->assertSame('model-x', $settings['chatmodel']);
		$this->assertSame([], $settings['tool_profiles']);
	}
}
