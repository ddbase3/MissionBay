<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Service;

use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Orchestrator\Service\AgentBatchResultService;
use PHPUnit\Framework\TestCase;

final class AgentBatchResultServiceTest extends TestCase {

	public function testAggregationRetainsPlainResultsAndGroupsChildrenAtFirstPosition(): void {
		$service = new AgentBatchResultService();
		$first = AgentToolResult::success('c2', 'delete', ['id' => 2], ['removed' => true], [
			'tool_call' => ['agent_batch' => ['index' => 2, 'label' => 'second', 'parent_call' => ['id' => 'parent', 'name' => 'batch', 'arguments' => ['count' => 2]]]]
		]);
		$second = AgentToolResult::failure('c1', 'delete', ['id' => 1], 'denied', 'No access', [
			'tool_call' => ['agent_batch' => ['index' => 1, 'label' => 'first', 'parent_call' => ['id' => 'parent', 'name' => 'batch', 'arguments' => ['count' => 2]]]]
		]);
		$plain = AgentToolResult::success('regular', 'lookup', [], 'read only');
		$result = $service->aggregate([$first, $plain, $second]);
		$this->assertCount(2, $result);
		$this->assertSame('parent', $result[0]->getCallId());
		$this->assertSame('batch', $result[0]->getToolName());
		$this->assertSame('partial', $result[0]->getOutput()['status']);
		$this->assertSame(2, $result[0]->getOutput()['total']);
		$this->assertSame(1, $result[0]->getOutput()['succeeded']);
		$this->assertSame('c1', $result[0]->getOutput()['items'][0]['call_id']);
		$this->assertSame('denied', $result[0]->getOutput()['items'][0]['error_code']);
		$this->assertSame('regular', $result[1]->getCallId());
	}

	public function testSuccessfulBatchDoesNotTreatBusinessFalseAsSuccess(): void {
		$service = new AgentBatchResultService();
		$parent = new AiToolCall('batch-1', 'execute_agent_tool_batch', []);
		$result = $service->aggregateForParent($parent, [
			AgentToolResult::success('c1', 'set', [], ['ok' => false, 'error_code' => 'conflict', 'error' => 'Already exists']),
			AgentToolResult::success('c2', 'set', [], ['ok' => true])
		]);
		$this->assertTrue($result->isSuccess());
		$this->assertFalse($result->getOutput()['ok']);
		$this->assertSame('partial', $result->getOutput()['status']);
		$this->assertSame('conflict', $result->getOutput()['items'][0]['error_code']);
		$this->assertSame('Already exists', $result->getOutput()['items'][0]['error']);
		$this->assertSame(1, $result->getOutput()['failed']);
	}

	public function testEmptyAndAllFailedGroupsHaveDistinctOutcomes(): void {
		$service = new AgentBatchResultService();
		$parent = new AiToolCall('p', 'batch', []);
		$empty = $service->aggregateForParent($parent, []);
		$this->assertSame('success', $empty->getOutput()['status']);
		$this->assertSame(0, $empty->getOutput()['total']);
		$failed = $service->aggregateForParent($parent, [AgentToolResult::failure('a', 'set', [], 'forbidden', 'Denied')]);
		$this->assertSame('failed', $failed->getOutput()['status']);
		$this->assertSame(1, $failed->getOutput()['failed']);
	}
}
