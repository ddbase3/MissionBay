<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Resource\DatabaseMemoryAgentResource;
use MissionBay\Service\AgentComponentPresetFlowExpander;
use MissionBay\Service\DatabaseMemoryEncryptionTargetProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseMemoryEncryptionTargetProviderTest extends TestCase {

	public function testConfiguredDatabaseMemoryPresetsAreDiscoveredByTheirRuntimeIds(): void {
		$presets = $this->createMock(IAgentComponentPresetRepository::class);
		$presets->method('getPresets')->willReturn([
			'conversation-database' => [
				'label' => 'Conversation Database', 'type' => DatabaseMemoryAgentResource::getName(), 'config' => []
			],
			'conversation-session' => [
				'label' => 'Session', 'type' => 'sessionmemoryagentresource', 'config' => []
			]
		]);

		$targets = (new DatabaseMemoryEncryptionTargetProvider($presets))->getTargets();
		$this->assertCount(1, $targets);
		$this->assertSame(
			DatabaseMemoryAgentResource::encryptionTargetId(
				AgentComponentPresetFlowExpander::getResourceIdForPreset('conversation-database')
			),
			$targets[0]->getTargetId()
		);
		$this->assertSame('Conversation Database', $targets[0]->getLabel());
	}

	public function testConflictingPresetRuntimeIdsAreRejectedInsteadOfMisassigned(): void {
		$presets = $this->createMock(IAgentComponentPresetRepository::class);
		$presets->method('getPresets')->willReturn([
			'alpha-a' => ['type' => DatabaseMemoryAgentResource::getName()],
			'alpha_a' => ['type' => DatabaseMemoryAgentResource::getName()]
		]);
		$this->expectException(\RuntimeException::class);
		(new DatabaseMemoryEncryptionTargetProvider($presets))->getTargets();
	}
}
