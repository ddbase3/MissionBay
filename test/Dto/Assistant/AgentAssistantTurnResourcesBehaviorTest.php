<?php declare(strict_types=1);

namespace MissionBay\Test\Dto\Assistant;

use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Api\IAiChatModel;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentProfileSelector;
use MissionBay\Api\IAgentTool;
use MissionBay\Dto\Assistant\AgentAssistantTurnResources;
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
