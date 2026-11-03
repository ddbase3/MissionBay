<?php declare(strict_types=1);

namespace MissionBay\Test\Assistant;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Api\IAgentAssistantFinalResponseService;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantTurnService;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Node\Ai\AiAssistantNode;
use MissionBay\Orchestrator\AgentToolOrchestratorResult;
use PHPUnit\Framework\TestCase;

final class AiAssistantNodeSuggestionsModeTest extends TestCase {

	public function testSuggestionsReadMemoryWithoutToolsOrMemoryWrites(): void {
		$turnService = new SuggestionsTurnServiceDouble();
		$memoryService = new SuggestionsMemoryServiceDouble();
		$node = new AiAssistantNode(
			$turnService,
			new SuggestionsFinalResponseServiceDouble(),
			$memoryService,
			new SuggestionsFallbackBuilderDouble(),
			'assistant'
		);

		$output = $node->execute(
			[
				'prompt' => 'Generate suggestions.',
				'mode' => 'suggestions'
			],
			['chatmodel' => [new SuggestionsChatModelDouble()]],
			new AgentContext()
		);

		$options = $turnService->getOptions();
		$this->assertNotNull($options);
		$this->assertSame('suggestions', $options->getMode());
		$this->assertFalse($options->areToolsEnabled());
		$this->assertTrue($options->isMemoryReadEnabled());
		$this->assertFalse($options->isMemoryWriteEnabled());
		$this->assertSame(0, $memoryService->getAppendCalls());
		$this->assertSame('Suggestions', $output['message']['content'] ?? null);
	}
}

require_once __DIR__ . '/SuggestionsTurnServiceDouble.php';
require_once __DIR__ . '/SuggestionsFinalResponseServiceDouble.php';
require_once __DIR__ . '/SuggestionsMemoryServiceDouble.php';
require_once __DIR__ . '/SuggestionsChatModelDouble.php';
require_once __DIR__ . '/SuggestionsFallbackBuilderDouble.php';
