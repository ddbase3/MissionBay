<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Assistant\AgentCapabilityDiscoveryResult;
use MissionBay\Orchestrator\Stage\AgentCapabilityDiscoveryStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentCapabilityDiscoveryStageTest extends TestCase {

	public function testSuccessfulDiscoveryIsPublishedAndApplied(): void {
		$events = [];
		$result = new AgentCapabilityDiscoveryResult(
			new AgentCapabilitySourceConfig(),
			instructions: ['Use project terminology.']
		);
		$context = new AgentContext(vars: [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_MODEL,
			AgentToolLoopContextKeys::CAPABILITY_DISCOVERY_APPLIED => false,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => '',
			AgentToolLoopContextKeys::CAPABILITY_DISCOVERY => $result,
			AgentToolLoopContextKeys::CAPABILITY_CATALOG => new AgentCapabilityCatalog(),
			AgentToolLoopContextKeys::EVENT_CALLBACK => static function(string $event, array $payload) use (&$events): void {
				$events[] = [$event, $payload];
			}
		]);
		$stage = new AgentCapabilityDiscoveryStage();

		$patch = $stage->process($context)->getPatch();

		$this->assertTrue($patch[AgentToolLoopContextKeys::CAPABILITY_DISCOVERY_APPLIED]);
		$this->assertSame(['Use project terminology.'], $patch[AgentToolLoopContextKeys::MODULE_INSTRUCTIONS]);
		$this->assertSame('capability.discovery', $events[0][0]);
		$this->assertSame(0, $events[0][1]['catalog_size']);
	}

	public function testStrictDiscoveryErrorFailsBeforeSelection(): void {
		$result = new AgentCapabilityDiscoveryResult(
			AgentCapabilitySourceConfig::fromArray(['tools' => ['missing'], 'strict' => true]),
			errors: ['Configured tool component was not found: missing']
		);
		$context = new AgentContext(vars: [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_MODEL,
			AgentToolLoopContextKeys::CAPABILITY_DISCOVERY_APPLIED => false,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => '',
			AgentToolLoopContextKeys::CAPABILITY_DISCOVERY => $result,
			AgentToolLoopContextKeys::CAPABILITY_CATALOG => new AgentCapabilityCatalog()
		]);
		$stage = new AgentCapabilityDiscoveryStage();

		$patch = $stage->process($context)->getPatch();

		$this->assertSame('capability_discovery_failed', $patch[AgentToolLoopContextKeys::FAILURE_CODE]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FAILED, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertFalse($patch[AgentToolLoopContextKeys::COMPLETED]);
	}
}
