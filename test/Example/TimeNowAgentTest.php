<?php declare(strict_types=1);

namespace MissionBay\Test\Example;

use PHPUnit\Framework\TestCase;
use MissionBay\Example\TimeNowAgent;
use MissionBay\Context\AgentContext;

final class TimeNowAgentTest extends TestCase {

	public function testGetName(): void {
		$this->assertSame('timenowagent', TimeNowAgent::getName());
	}

	public function testIdCanBeSetAndRetrieved(): void {
		$agent = new TimeNowAgent();
		$agent->setId('agent-1');

		$this->assertSame('agent-1', $agent->getId());
	}

	public function testContextCanBeSetAndRetrieved(): void {
		$agent = new TimeNowAgent();

		$context = new class extends AgentContext {};
		$agent->setContext($context);

		$this->assertSame($context, $agent->getContext());
	}

	public function testRunReturnsIso8601Time(): void {
		$agent = new TimeNowAgent();

		$result = $agent->run();

		$this->assertArrayHasKey('time', $result);
		$this->assertIsString($result['time']);

		// validate ISO 8601 (date('c'))
		$dt = \DateTime::createFromFormat(\DateTime::ATOM, $result['time']);
		$this->assertInstanceOf(\DateTime::class, $dt);
	}

	public function testFunctionMetadata(): void {
		$agent = new TimeNowAgent();

		$this->assertSame('timenow', $agent->getFunctionName());
		$this->assertSame(
			'Returns the current server time in ISO 8601 format.',
			$agent->getDescription()
		);
	}

	public function testInputSpecIsEmpty(): void {
		$agent = new TimeNowAgent();

		$this->assertSame([], $agent->getInputSpec());
	}

	public function testOutputSpecContainsTimeField(): void {
		$agent = new TimeNowAgent();

		$spec = $agent->getOutputSpec();

		$this->assertArrayHasKey('time', $spec);
		$this->assertSame('string', $spec['time']['type']);
	}

	public function testDefaultConfigIsEmpty(): void {
		$agent = new TimeNowAgent();

		$this->assertSame([], $agent->getDefaultConfig());
	}

	public function testCategoryVersionAndTags(): void {
		$agent = new TimeNowAgent();

		$this->assertSame('Utility', $agent->getCategory());
		$this->assertSame('1.0.0', $agent->getVersion());
		$this->assertSame(['time', 'datetime', 'now'], $agent->getTags());
	}

	public function testAsyncAndDependencies(): void {
		$agent = new TimeNowAgent();

		$this->assertFalse($agent->supportsAsync());
		$this->assertSame([], $agent->getDependencies());
	}
}
