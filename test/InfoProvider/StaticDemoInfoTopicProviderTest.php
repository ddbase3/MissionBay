<?php declare(strict_types=1);

namespace MissionBay\Test\InfoProvider;

use MissionBay\Dto\AgentInfoRequest;
use MissionBay\InfoProvider\StaticDemoInfoTopicProvider;
use PHPUnit\Framework\TestCase;

final class StaticDemoInfoTopicProviderTest extends TestCase {

	public function testTopicNamesAndAliasesAreNormalized(): void {
		$provider = new StaticDemoInfoTopicProvider();
		$this->assertSame('demo', $provider->getTopic());
		$this->assertTrue($provider->supports(' EXAMPLE '));
		$this->assertTrue($provider->supports('test'));
		$this->assertFalse($provider->supports('not-demo'));
		$this->assertSame(1, $provider->getPriority());
	}

	public function testFindReturnsPagesAndEmptySearch(): void {
		$provider = new StaticDemoInfoTopicProvider();
		$first = $provider->handle(new AgentInfoRequest('demo', '', 'find', 1, 0));
		$this->assertTrue($first->success);
		$this->assertSame(1, $first->paging['returned']);
		$this->assertSame('alpha', $first->items[0]['id']);
		$second = $provider->handle(new AgentInfoRequest('demo', '', 'find', 1, 1));
		$this->assertSame('beta', $second->items[0]['id']);
		$missing = $provider->handle(new AgentInfoRequest('demo', 'non-existing', 'find'));
		$this->assertSame([], $missing->items);
		$this->assertSame(0, $missing->paging['total']);
	}

	public function testDetailHandlesMissingUnknownAndExactQuery(): void {
		$provider = new StaticDemoInfoTopicProvider();
		$this->assertSame('missing_query', $provider->handle(new AgentInfoRequest('demo', ' ', 'detail'))->errors[0]['code']);
		$this->assertSame('not_found', $provider->handle(new AgentInfoRequest('demo', 'not-existing', 'detail'))->errors[0]['code']);
		$exact = $provider->handle(new AgentInfoRequest('demo', ' ALPHA ', 'detail'));
		$this->assertTrue($exact->success);
		$this->assertSame('alpha', $exact->detail['id']);
	}

	public function testSummaryAndLinksDoNotAccessExternalServices(): void {
		$provider = new StaticDemoInfoTopicProvider();
		$summary = $provider->handle(new AgentInfoRequest('demo', '', 'summary'));
		$this->assertSame('staticdemoinfotopicprovider', $summary->detail['provider']);
		$this->assertSame(['find', 'summary', 'detail', 'link'], $summary->items[0]['supported_scopes']);
		$link = $provider->handle(new AgentInfoRequest('demo', '', 'link'));
		$this->assertSame('topics', $link->links[0]['tool_call']['topic']);
		$this->assertTrue($link->success);
	}
}
