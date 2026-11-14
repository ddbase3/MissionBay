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

use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Resource\DatabaseMemoryAgentResource;
use Base3\MissionBay\Service\AgentComponentPresetFlowExpander;
use Base3\MissionBay\Service\DatabaseMemoryEncryptionTargetProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseMemoryEncryptionTargetProviderTest extends TestCase {

	public function testConfiguredDatabaseMemoryPresetsAreDiscoveredByTheirRuntimeIds(): void {
		$presets = $this->createStub(IAgentComponentPresetRepository::class);
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
		$presets = $this->createStub(IAgentComponentPresetRepository::class);
		$presets->method('getPresets')->willReturn([
			'alpha-a' => ['type' => DatabaseMemoryAgentResource::getName()],
			'alpha_a' => ['type' => DatabaseMemoryAgentResource::getName()]
		]);
		$this->expectException(\RuntimeException::class);
		(new DatabaseMemoryEncryptionTargetProvider($presets))->getTargets();
	}
}
