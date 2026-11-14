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

namespace Base3\MissionBay\Test\Dto\Assistant;

use Base3\AssistantFoundation\Api\IAgentContextContributor;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentProfileSelector;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use PHPUnit\Framework\TestCase;

final class AgentAssistantTurnResourcesBehaviorTest extends TestCase {

	public function testEmptyOptionalResourcesPreserveTheModel(): void {
		$model = $this->createStub(IAiChatModel::class);
		$result = new AgentAssistantTurnResources($model);
		$this->assertSame($model, $result->getModel());
		$this->assertSame([], $result->getMemories());
		$this->assertSame([], $result->getTools());
		$this->assertSame([], $result->getContextContributors());
		$this->assertNull($result->getLogger());
		$this->assertNull($result->getProfileSelector());
	}

	public function testEachDependencyRetainsItsOwnResourceCategory(): void {
		$model = $this->createStub(IAiChatModel::class);
		$memory = $this->createStub(IAgentMemory::class);
		$tool = $this->createStub(IAgentTool::class);
		$contributor = $this->createStub(IAgentContextContributor::class);
		$logger = $this->createStub(ILogger::class);
		$selector = $this->createStub(IAgentProfileSelector::class);
		$result = new AgentAssistantTurnResources($model, [$memory], [$tool], $logger, $selector, [$contributor]);
		$this->assertSame($model, $result->getModel());
		$this->assertSame([$memory], $result->getMemories());
		$this->assertSame([$tool], $result->getTools());
		$this->assertSame([$contributor], $result->getContextContributors());
		$this->assertSame($logger, $result->getLogger());
		$this->assertSame($selector, $result->getProfileSelector());
	}
}
