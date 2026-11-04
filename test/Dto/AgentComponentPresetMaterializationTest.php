<?php declare(strict_types=1);

namespace MissionBay\Test\Dto;

use MissionBay\Api\IAgentResource;
use MissionBay\Dto\AgentComponentPresetMaterialization;
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
