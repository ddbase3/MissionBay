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

namespace Base3\MissionBay\Test\Mcp;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentPromptProvider;
use Base3\MissionBay\Api\IAgentResourceProvider;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Mcp\McpPromptCatalog;
use Base3\MissionBay\Mcp\McpResourceCatalog;
use PHPUnit\Framework\TestCase;

final class McpContentCatalogTest extends TestCase {

	public function testResourceCatalogPreservesMetadataAndBinaryContents(): void {
		$catalog = new McpResourceCatalog(
			[new McpContentResourceProvider('provider-one')],
			new AgentContext(),
			new McpContentNullLogger()
		);

		$list = $catalog->listResources();
		$this->assertSame('repo://image', $list['resources'][0]['uri']);
		$this->assertSame(42, $list['resources'][0]['size']);
		$this->assertSame(['audience' => ['assistant']], $list['resources'][0]['annotations']);
		$this->assertSame([['src' => 'icon.svg']], $list['resources'][0]['icons']);

		$templates = $catalog->listResourceTemplates();
		$this->assertSame('repo://file/{path}', $templates['resourceTemplates'][0]['uriTemplate']);

		$read = $catalog->readResource('repo://image');
		$this->assertSame('base64-data', $read['contents'][0]['blob']);
		$this->assertSame('image/png', $read['contents'][0]['mimeType']);
		$this->assertSame(['source' => 'remote'], $read['contents'][0]['_meta']);
		$this->assertSame(['request' => 'metadata'], $read['_meta']);
	}

	public function testPromptCatalogPreservesNonTextContentBlocks(): void {
		$catalog = new McpPromptCatalog(
			[new McpContentPromptProvider('prompt-provider')],
			new AgentContext(),
			new McpContentNullLogger()
		);

		$list = $catalog->listPrompts();
		$this->assertSame('review_image', $list['prompts'][0]['name']);
		$this->assertSame([['src' => 'prompt.svg']], $list['prompts'][0]['icons']);

		$result = $catalog->getPrompt('review_image', []);
		$this->assertSame('image', $result['messages'][0]['content']['type']);
		$this->assertSame('image-data', $result['messages'][0]['content']['data']);
		$this->assertSame('image/png', $result['messages'][0]['content']['mimeType']);
		$this->assertSame(['prompt' => 'metadata'], $result['_meta']);
	}

}

require_once __DIR__ . '/McpContentResourceProvider.php';
require_once __DIR__ . '/McpContentPromptProvider.php';
require_once __DIR__ . '/McpContentNullLogger.php';
