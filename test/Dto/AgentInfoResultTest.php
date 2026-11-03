<?php declare(strict_types=1);

namespace MissionBay\Test\Dto;

use MissionBay\Dto\AgentInfoResult;
use PHPUnit\Framework\TestCase;

final class AgentInfoResultTest extends TestCase {

	public function testEmptyValuesAreOmittedUnlessRequested(): void {
		$result = new AgentInfoResult(topic: 'system', scope: 'global');
		$this->assertSame(['success' => true, 'topic' => 'system', 'scope' => 'global'], $result->toArray());
		$this->assertArrayHasKey('paging', $result->toArray(true));
		$this->assertArrayHasKey('errors', $result->toArray(true));
		$this->assertSame([], $result->toArray(true)['errors']);
	}

	public function testSuccessFactoryPreservesDetailPagingAndProvider(): void {
		$result = AgentInfoResult::createSuccess('users', 'course', 'Found', [['id' => 1]], ['total' => 1], [['href' => '/a']], ['next' => 'p2'], 'host');
		$this->assertTrue($result->success);
		$this->assertSame('host', $result->toArray()['provider']);
		$this->assertSame([['id' => 1]], $result->toArray()['items']);
		$this->assertSame(['next' => 'p2'], $result->toArray()['paging']);
		$this->assertArrayNotHasKey('errors', $result->toArray());
	}

	public function testErrorFactoryAndAddErrorPreserveStructuredFailure(): void {
		$result = AgentInfoResult::createError('users', 'course', 'not_found', 'No record', ['Try another id'], 'host');
		$result->addError('invalid', 'Bad id', ['id' => 3]);
		$this->assertFalse($result->success);
		$this->assertSame('not_found', $result->toArray()['errors'][0]['code']);
		$this->assertSame(['id' => 3], $result->toArray()['errors'][1]['context']);
		$this->assertSame(['Try another id'], $result->suggestions);
	}
}
