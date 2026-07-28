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

final class McpContentResourceProvider implements IAgentResourceProvider {

	public function __construct(private readonly string $name) {}

	public static function getName(): string {
		return 'mcpcontentresourceprovider';
	}

	public function getResourceDefinitions(IAgentContext $context): array {
		return [
			[
				'uri' => 'repo://image',
				'name' => 'image',
				'title' => 'Repository image',
				'mimeType' => 'image/png',
				'size' => 42,
				'annotations' => ['audience' => ['assistant']],
				'icons' => [['src' => 'icon.svg']],
				'_meta' => ['provider' => $this->name]
			],
			[
				'uriTemplate' => 'repo://file/{path}',
				'name' => 'file',
				'title' => 'Repository file',
				'mimeType' => 'application/octet-stream'
			]
		];
	}

	public function readResource(string $uri, IAgentContext $context): ?array {
		if($uri !== 'repo://image') {
			return null;
		}

		return [
			'contents' => [[
				'uri' => $uri,
				'mimeType' => 'image/png',
				'blob' => 'base64-data',
				'_meta' => ['source' => 'remote']
			]],
			'_meta' => ['request' => 'metadata']
		];
	}
}

final class McpContentPromptProvider implements IAgentPromptProvider {

	public function __construct(private readonly string $providerName) {}

	public static function getName(): string {
		return 'mcpcontentpromptprovider';
	}

	public function getPromptDefinitions(IAgentContext $context): array {
		return [[
			'name' => 'review_image',
			'title' => 'Review image',
			'description' => 'Reviews one image.',
			'icons' => [['src' => 'prompt.svg']],
			'_meta' => ['provider' => $this->providerName]
		]];
	}

	public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
		if($name !== 'review_image') {
			return null;
		}

		return [
			'messages' => [[
				'role' => 'user',
				'content' => [
					'type' => 'image',
					'data' => 'image-data',
					'mimeType' => 'image/png'
				]
			]],
			'_meta' => ['prompt' => 'metadata']
		];
	}
}

final class McpContentNullLogger implements ILogger {

	public function emergency(string|\Stringable $message, array $context = []): void {}
	public function alert(string|\Stringable $message, array $context = []): void {}
	public function critical(string|\Stringable $message, array $context = []): void {}
	public function error(string|\Stringable $message, array $context = []): void {}
	public function warning(string|\Stringable $message, array $context = []): void {}
	public function notice(string|\Stringable $message, array $context = []): void {}
	public function info(string|\Stringable $message, array $context = []): void {}
	public function debug(string|\Stringable $message, array $context = []): void {}
	public function logLevel(string $level, string|\Stringable $message, array $context = []): void {}
	public function log(string $scope, string $log, ?int $timestamp = null): bool { return true; }
	public function getScopes(): array { return []; }
	public function getNumOfScopes(): int { return 0; }
	public function getLogs(string $scope, int $num = 50, bool $reverse = true): array { return []; }
}
