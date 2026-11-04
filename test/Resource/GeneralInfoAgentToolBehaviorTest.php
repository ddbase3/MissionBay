<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentContext;
use Base3\Api\IClassMap;
use MissionBay\Context\AgentContext;
use MissionBay\InfoProvider\StaticDemoInfoTopicProvider;
use MissionBay\InfoProvider\SystemInfoTopicProvider;
use MissionBay\Resource\GeneralInfoAgentTool;
use PHPUnit\Framework\TestCase;

final class GeneralInfoAgentToolBehaviorTest extends TestCase {

	public function testToolDefinitionIsReadOnlyAndRequiresTopic(): void {
		$definitions = $this->tool()->getToolDefinitions();
		$this->assertCount(1, $definitions);
		$this->assertSame('general_info', $definitions[0]['function']['name']);
		$this->assertTrue($definitions[0]['readOnlyHint']);
		$this->assertFalse($definitions[0]['mutation']);
		$this->assertFalse($definitions[0]['requiresApproval']);
		$this->assertSame(['topic'], $definitions[0]['function']['parameters']['required']);
	}

	public function testUnrecognizedFunctionCannotBeExecuted(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->tool()->callTool('admin_delete', [], $this->context());
	}

	public function testTopicIsRequired(): void {
		$result = $this->call([]);
		$this->assertFalse($result['success']);
		$this->assertSame('missing_topic', $result['errors'][0]['code']);
		$this->assertSame(['topics'], $result['suggestions']);
	}

	public function testWhitespaceTopicIsRejected(): void {
		$this->assertSame('missing_topic', $this->call(['topic' => '  '])['errors'][0]['code']);
	}

	public function testUnsupportedScopeProducesStructuredError(): void {
		$result = $this->call(['topic' => 'demo', 'scope' => 'delete']);
		$this->assertSame('unsupported_scope', $result['errors'][0]['code']);
		$this->assertContains('detail', $result['suggestions']);
	}

	public function testBlankScopeDefaultsToSummary(): void {
		$this->assertSame('summary', $this->call(['topic' => 'demo', 'scope' => ' '])['scope']);
	}

	public function testTopicDiscoveryReportsEmptyCatalog(): void {
		$result = $this->call(['topic' => 'topics'], []);
		$this->assertTrue($result['success']);
		$this->assertSame(0, $result['paging']['total']);
		$this->assertSame('No info topic providers available.', $result['message']);
	}

	public function testTopicDiscoveryUsesPriorityOrder(): void {
		$result = $this->call(['topic' => 'topics'], [new StaticDemoInfoTopicProvider(), new SystemInfoTopicProvider()]);
		$this->assertSame(['system', 'demo'], array_column($result['items'], 'id'));
		$this->assertSame(2, $result['paging']['total']);
	}

	public function testTopicDiscoverySupportsPagination(): void {
		$result = $this->call(['topic' => 'topics', 'limit' => 1, 'offset' => 1], [new SystemInfoTopicProvider(), new StaticDemoInfoTopicProvider()]);
		$this->assertSame(['demo'], array_column($result['items'], 'id'));
		$this->assertSame(['offset' => 1, 'limit' => 1, 'total' => 2, 'returned' => 1], $result['paging']);
	}

	public function testTopicDiscoveryClampsLimitAndOffset(): void {
		$result = $this->call(['topic' => 'topics', 'limit' => -10, 'offset' => -3], [new StaticDemoInfoTopicProvider(), new SystemInfoTopicProvider()]);
		$this->assertSame(1, $result['paging']['limit']);
		$this->assertSame(0, $result['paging']['offset']);
	}

	public function testTopicDiscoveryClampsMaximumPageSize(): void {
		$result = $this->call(['topic' => 'topics', 'limit' => 10000]);
		$this->assertSame(25, $result['paging']['limit']);
	}

	public function testTopicDiscoveryBeyondLastPageReturnsNoItems(): void {
		$result = $this->call(['topic' => 'topics', 'offset' => 100], [new StaticDemoInfoTopicProvider()]);
		$this->assertSame(1, $result['paging']['total']);
		$this->assertSame(0, $result['paging']['returned']);
	}

	public function testUnknownTopicGivesDiscoverySuggestions(): void {
		$result = $this->call(['topic' => 'librari'], [new GeneralInfoTestTopicProvider()]);
		$this->assertFalse($result['success']);
		$this->assertSame('unknown_topic', $result['errors'][0]['code']);
		$this->assertSame('library', $result['suggestions'][0]);
		$this->assertContains('topics', $result['suggestions']);
	}

	public function testCaseAndSpaceNormalizationSelectAlias(): void {
		$provider = new GeneralInfoTestTopicProvider();
		$result = $this->call(['topic' => '  REFERENCE  '], [$provider]);
		$this->assertTrue($result['success']);
		$this->assertSame('reference', $provider->lastRequest?->topic);
	}

	public function testProviderCanHandlePrimaryTopicWithoutSupportsOverride(): void {
		$provider = new GeneralInfoTestTopicProvider();
		$this->assertSame('library', $this->call(['topic' => 'library'], [$provider])['topic']);
		$this->assertSame(1, $provider->calls);
	}

	public function testProviderSupportsCanResolveAlternateTopic(): void {
		$provider = new GeneralInfoTestTopicProvider();
		$provider->supportsAll = true;
		$this->assertTrue($this->call(['topic' => 'other-topic'], [$provider])['success']);
	}

	public function testProviderSelectionPrefersHighestPriority(): void {
		$lower = new GeneralInfoTestTopicProvider('library', 1);
		$higher = new GeneralInfoTestTopicProvider('library', 20);
		$this->call(['topic' => 'library'], [$lower, $higher]);
		$this->assertSame(0, $lower->calls);
		$this->assertSame(1, $higher->calls);
	}

	public function testProviderReceivesNormalizedPaginationAndQuery(): void {
		$provider = new GeneralInfoTestTopicProvider();
		$this->call(['topic' => ' library ', 'query' => ' Book A ', 'scope' => 'detail', 'limit' => 999, 'offset' => -5], [$provider]);
		$this->assertSame('Book A', $provider->lastRequest?->query);
		$this->assertSame('detail', $provider->lastRequest?->scope);
		$this->assertSame(25, $provider->lastRequest?->limit);
		$this->assertSame(0, $provider->lastRequest?->offset);
	}

	public function testMissingProviderResultMetadataIsFilledByTool(): void {
		$provider = new GeneralInfoTestTopicProvider();
		$result = $this->call(['topic' => 'library', 'scope' => 'find'], [$provider]);
		$this->assertSame('library', $result['topic']);
		$this->assertSame('find', $result['scope']);
		$this->assertSame('generalinfotesttopicprovider', $result['provider']);
		$this->assertSame('Library information', $result['message']);
	}

	public function testProviderResultMetadataIsNotOverwrittenWhenSet(): void {
		$provider = new GeneralInfoTestTopicProvider();
		$provider->response = \MissionBay\Dto\AgentInfoResult::createSuccess('official', 'detail', 'From provider', provider: 'authoritative');
		$result = $this->call(['topic' => 'library'], [$provider]);
		$this->assertSame('official', $result['topic']);
		$this->assertSame('detail', $result['scope']);
		$this->assertSame('authoritative', $result['provider']);
		$this->assertSame('From provider', $result['message']);
	}

	public function testProviderItemsAreHardLimitedByRequest(): void {
		$provider = new GeneralInfoTestTopicProvider();
		$provider->response = \MissionBay\Dto\AgentInfoResult::createSuccess('', '', '', items: [['n' => 1], ['n' => 2], ['n' => 3]]);
		$result = $this->call(['topic' => 'library', 'limit' => 2], [$provider]);
		$this->assertSame([['n' => 1], ['n' => 2]], $result['items']);
		$this->assertTrue($result['paging']['truncated']);
		$this->assertSame(3, $result['paging']['total']);
	}

	public function testExistingProviderPagingIsPreservedWhenTruncatingItems(): void {
		$provider = new GeneralInfoTestTopicProvider();
		$provider->response = \MissionBay\Dto\AgentInfoResult::createSuccess('', '', '', items: [[1], [2]], paging: ['cursor' => 'provider-owned']);
		$result = $this->call(['topic' => 'library', 'limit' => 1], [$provider]);
		$this->assertSame(['cursor' => 'provider-owned'], $result['paging']);
		$this->assertCount(1, $result['items']);
	}

	public function testLinksAreLimitedToTwentyFive(): void {
		$provider = new GeneralInfoTestTopicProvider();
		$provider->response = \MissionBay\Dto\AgentInfoResult::createSuccess('', '', '', links: array_fill(0, 30, ['url' => 'https://example.test']));
		$result = $this->call(['topic' => 'library'], [$provider]);
		$this->assertCount(25, $result['links']);
		$this->assertTrue($result['paging']['links_truncated']);
		$this->assertSame(30, $result['paging']['links_total']);
	}

	public function testProviderExceptionIsRedactedAsToolError(): void {
		$provider = new GeneralInfoTestTopicProvider();
		$provider->throwOnHandle = true;
		$result = $this->call(['topic' => 'library'], [$provider]);
		$this->assertFalse($result['success']);
		$this->assertSame('tool_error', $result['errors'][0]['code']);
		$this->assertStringNotContainsString('private implementation details', json_encode($result));
	}

	public function testNonProviderClassMapResultsAreIgnored(): void {
		$result = $this->call(['topic' => 'topics'], [new \stdClass(), new StaticDemoInfoTopicProvider()]);
		$this->assertSame(['demo'], array_column($result['items'], 'id'));
	}

	public function testProviderDiscoveryIsCachedWithinResource(): void {
		$calls = 0;
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturnCallback(static function(string $interface) use (&$calls): array {
			$calls++;
			return [new StaticDemoInfoTopicProvider()];
		});
		$tool = new GeneralInfoAgentTool($classMap, 'general-info-unit');
		$tool->callTool('general_info', ['topic' => 'topics'], $this->context());
		$tool->getResourceDefinitions($this->context());
		$tool->callTool('general_info', ['topic' => 'demo'], $this->context());
		$this->assertSame(1, $calls);
	}

	public function testTopicResourceListIncludesEncodedUris(): void {
		$provider = new GeneralInfoTestTopicProvider('custom topic', 4);
		$definitions = $this->tool([$provider])->getResourceDefinitions($this->context());
		$this->assertSame('generalinfo://topics', $definitions[0]['uri']);
		$this->assertSame('generalinfo://topic/{topic}', $definitions[1]['uriTemplate']);
		$this->assertSame('generalinfo://topic/custom_topic', $definitions[2]['uri']);
	}

	public function testUnknownResourceSchemeReturnsNull(): void {
		$this->assertNull($this->tool()->readResource('agent://topics', $this->context()));
	}

	public function testTopicsResourceProvidesJsonMetadata(): void {
		$read = $this->tool([new StaticDemoInfoTopicProvider()])->readResource('generalinfo://topics', $this->context());
		$this->assertSame('application/json', $read['contents'][0]['mimeType']);
		$data = json_decode($read['contents'][0]['text'], true);
		$this->assertSame('topics', $data['topic']);
		$this->assertSame('demo', $data['items'][0]['id']);
	}

	public function testAliasResourceResolvesProviderDetails(): void {
		$read = $this->tool([new GeneralInfoTestTopicProvider()])->readResource('generalinfo://topic/books', $this->context());
		$data = json_decode($read['contents'][0]['text'], true);
		$this->assertSame('library', $data['topic']);
		$this->assertSame(['books', 'reference'], $data['aliases']);
		$this->assertSame('generalinfotesttopicprovider', $data['provider']);
	}

	public function testUnknownTopicResourceHasSuggestions(): void {
		$read = $this->tool([new GeneralInfoTestTopicProvider()])->readResource('generalinfo://topic/librari', $this->context());
		$data = json_decode($read['contents'][0]['text'], true);
		$this->assertSame('unknown_topic', $data['code']);
		$this->assertContains('library', $data['suggestions']);
	}

	public function testPromptDefinitionsOfferDiscoveryAndLookup(): void {
		$this->assertSame(['generalinfo_discover_topics', 'generalinfo_lookup'], array_column($this->tool()->getPromptDefinitions($this->context()), 'name'));
	}

	public function testUnknownPromptReturnsNull(): void {
		$this->assertNull($this->tool()->getPrompt('unauthorized_admin', [], $this->context()));
	}

	public function testDiscoveryPromptContainsReadOnlyLookupInstructions(): void {
		$prompt = $this->tool()->getPrompt(' generalinfo_discover_topics ', [], $this->context());
		$this->assertStringContainsString('topic "topics"', $prompt['messages'][0]['content']['text']);
	}

	public function testLookupPromptNormalizesScopeAndIncludesQuery(): void {
		$prompt = $this->tool()->getPrompt('generalinfo_lookup', ['topic' => ' CUSTOM TOPIC ', 'query' => ' Book ID ', 'scope' => 'not-valid'], $this->context());
		$text = $prompt['messages'][0]['content']['text'];
		$this->assertStringContainsString('Preferred topic: custom_topic', $text);
		$this->assertStringContainsString('Book ID', $text);
		$this->assertStringContainsString('Preferred scope: summary', $text);
	}

	private function context(): IAgentContext {
		return new AgentContext();
	}

	private function call(array $args, array $providers = []): array {
		return $this->tool($providers)->callTool('general_info', $args, $this->context());
	}

	private function tool(array $providers = []): GeneralInfoAgentTool {
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn($providers);
		return new GeneralInfoAgentTool($classMap, 'general-info-unit');
	}
}
