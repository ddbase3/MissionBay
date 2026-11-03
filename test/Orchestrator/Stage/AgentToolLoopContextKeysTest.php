<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Stage;

use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentToolLoopContextKeysTest extends TestCase {

	public function testTemporaryKeysAreUniqueAndNamespaced(): void {
		$keys = AgentToolLoopContextKeys::getTemporaryRuntimeKeys();
		$this->assertNotEmpty($keys);
		$this->assertSame($keys, array_values(array_unique($keys)));
		foreach ($keys as $key) {
			$this->assertTrue(str_starts_with($key, AgentToolLoopContextKeys::PREFIX));
		}
	}

	public function testTemporaryKeysIncludeTransientCapabilitiesButNotPersistentMessages(): void {
		$keys = AgentToolLoopContextKeys::getTemporaryRuntimeKeys();
		foreach ([
			AgentToolLoopContextKeys::MODEL,
			AgentToolLoopContextKeys::TOOLS,
			AgentToolLoopContextKeys::TOOL_DEFINITIONS,
			AgentToolLoopContextKeys::MUTATION_TOOL_NAMES,
			AgentToolLoopContextKeys::RESUME_HANDLE,
			AgentToolLoopContextKeys::EXECUTION_STATUS
		] as $key) {
			$this->assertTrue(in_array($key, $keys, true));
		}
		$this->assertFalse(in_array(AgentToolLoopContextKeys::MESSAGES, $keys, true));
		$this->assertFalse(in_array(AgentToolLoopContextKeys::STAGE_TRACE, $keys, true));
	}
}
