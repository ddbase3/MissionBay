<?php declare(strict_types=1);

namespace MissionBay\Test\Dto;

use MissionBay\Dto\AgentFlowCompilation;
use PHPUnit\Framework\TestCase;

final class AgentFlowCompilationResultTest extends TestCase {

	public function testResultPreservesCompiledGraphAndWarnings(): void {
		$flow = ['nodes' => [['id' => 'start']], 'connections' => []];
		$warnings = ['unused resource', 'incomplete optional port'];
		$result = new AgentFlowCompilation($flow, $warnings);
		$this->assertSame($flow, $result->getFlow());
		$this->assertSame($warnings, $result->getWarnings());
	}

	public function testResultDefaultsToNoWarningsAndAnEmptyGraph(): void {
		$result = new AgentFlowCompilation([]);
		$this->assertSame([], $result->getFlow());
		$this->assertSame([], $result->getWarnings());
	}
}
