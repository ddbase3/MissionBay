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

namespace Base3\MissionBay\Test\Dto;

use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Dto\AgentComponentPresetMaterialization;
use PHPUnit\Framework\TestCase;

final class AgentComponentPresetMaterializationTest extends TestCase {

	public function testReadyResultRequiresAResourceAndNoWarnings(): void {
		$resource = $this->createStub(IAgentResource::class);
		$result = new AgentComponentPresetMaterialization('preset', ['id' => 'preset'], $resource, null, null, null, ['retrieval'], [], ['model' => ['one']]);
		$this->assertTrue($result->isReady());
		$this->assertSame('preset', $result->getPresetId());
		$this->assertSame(['id' => 'preset'], $result->getPreset());
		$this->assertSame($resource, $result->getResource());
		$this->assertSame(['retrieval'], $result->getCapabilities());
		$this->assertSame(['model' => ['one']], $result->getDocks());
	}

	public function testResourceWithWarningsIsNotReady(): void {
		$resource = $this->createStub(IAgentResource::class);
		$result = new AgentComponentPresetMaterialization('p', [], $resource, null, null, null, [], ['missing dependency']);
		$this->assertFalse($result->isReady());
		$this->assertSame(['missing dependency'], $result->getWarnings());
	}

	public function testMissingResourceIsNotReadyEvenIfOtherFieldsArePresent(): void {
		$result = new AgentComponentPresetMaterialization('p', [], null, null, null, null, ['tool']);
		$this->assertFalse($result->isReady());
		$this->assertNull($result->getTool());
		$this->assertNull($result->getMemory());
		$this->assertNull($result->getContextContributor());
	}
}
