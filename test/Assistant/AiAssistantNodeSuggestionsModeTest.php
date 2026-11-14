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

namespace Base3\MissionBay\Test\Assistant;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentAssistantFallbackBuilder;
use Base3\MissionBay\Api\IAgentAssistantFinalResponseService;
use Base3\MissionBay\Api\IAgentAssistantMemoryService;
use Base3\MissionBay\Api\IAgentAssistantTurnService;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use Base3\MissionBay\Node\Ai\AiAssistantNode;
use Base3\MissionBay\Orchestrator\AgentToolOrchestratorResult;
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
