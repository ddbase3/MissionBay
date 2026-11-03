<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Context\AgentContext;
use MissionBay\Mcp\McpPromptCatalog;
use MissionBay\Mcp\McpResourceCatalog;
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
