<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Mcp\McpPromptCatalog;
use PHPUnit\Framework\TestCase;

final class McpPromptCatalogTest extends TestCase {

	public function testEmptyCatalogAndUnknownPromptAreHandled(): void {
		$catalog = $this->catalog([]);
		$this->assertSame(['prompts' => []], $catalog->listPrompts());
		try {
			$catalog->getPrompt('unknown', []);
			$this->fail('Unknown prompt must be rejected.');
		}
		catch (\InvalidArgumentException $exception) {
			$this->assertStringContainsString('Unknown MCP prompt', $exception->getMessage());
		}
	}

	public function testListingNormalizesDefinitionsAndDeduplicatesByName(): void {
		$provider = $this->provider([
			['name' => ' first ', 'title' => 'First', 'description' => ' Example ', 'arguments' => [
				['name' => ' query ', 'description' => ' Search ', 'required' => 'YES'],
				['name' => '', 'required' => true],
				['name' => 'limit', 'required' => 0]
			], '_meta' => ['origin' => 'local']],
			['name' => 'first', 'title' => 'Duplicate'],
			['name' => ' '],
			['description' => 'Missing name'],
			'ignored',
			['name' => 'second', 'icons' => [['src' => 'icon.svg']]]
		]);
		$catalog = $this->catalog([$provider]);
		$prompts = $catalog->listPrompts()['prompts'];
		$this->assertCount(2, $prompts);
		$this->assertSame('first', $prompts[0]['name']);
		$this->assertSame('First', $prompts[0]['title']);
		$this->assertSame('Example', $prompts[0]['description']);
		$this->assertSame(['origin' => 'local'], $prompts[0]['_meta']);
		$this->assertSame(['name' => 'query', 'description' => 'Search', 'required' => true], $prompts[0]['arguments'][0]);
		$this->assertSame(['name' => 'limit', 'required' => false], $prompts[0]['arguments'][1]);
		$this->assertSame('second', $prompts[1]['name']);
	}

	public function testPaginationUsesStableOffsetCursor(): void {
		$definitions = [];
		for ($index = 0; $index < 53; $index++) {
			$definitions[] = ['name' => 'prompt_' . $index];
		}
		$catalog = $this->catalog([$this->provider($definitions)]);
		$first = $catalog->listPrompts();
		$this->assertCount(50, $first['prompts']);
		$this->assertSame('50', $first['nextCursor']);
		$second = $catalog->listPrompts('50');
		$this->assertCount(3, $second['prompts']);
		$this->assertArrayNotHasKey('nextCursor', $second);
		$this->assertSame('prompt_50', $second['prompts'][0]['name']);
	}

	public function testInvalidCursorAndEmptyNameAreRejected(): void {
		$catalog = $this->catalog([]);
		foreach (['abc', '-1', '1.2'] as $cursor) {
			try {
				$catalog->listPrompts($cursor);
				$this->fail('Cursor must be numeric and nonnegative.');
			}
			catch (\InvalidArgumentException $exception) {
				$this->assertStringContainsString('cursor', $exception->getMessage());
			}
		}
		try {
			$catalog->getPrompt('  ', []);
			$this->fail('Missing prompt name must be rejected.');
		}
		catch (\InvalidArgumentException $exception) {
			$this->assertStringContainsString('name', $exception->getMessage());
		}
	}

	public function testGettingPromptNormalizesTextAndRoleAndPreservesMetadata(): void {
		$provider = $this->provider([], [
			'messages' => [
				['role' => '', 'content' => 'Hello'],
				['role' => 'assistant', 'content' => ['text' => 'Answer']],
				['role' => 'user', 'content' => ['type' => 'image', 'data' => 'a']],
				['role' => 'user', 'content' => false]
			],
			'description' => ' A prompt ',
			'_meta' => ['source' => 'demo']
		]);
		$result = $this->catalog([$provider])->getPrompt('  demo  ', ['subject' => 'test']);
		$this->assertCount(3, $result['messages']);
		$this->assertSame(['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hello']], $result['messages'][0]);
		$this->assertSame(['role' => 'assistant', 'content' => ['text' => 'Answer', 'type' => 'text']], $result['messages'][1]);
		$this->assertSame('A prompt', $result['description']);
		$this->assertSame(['source' => 'demo'], $result['_meta']);
	}

	public function testPromptWithoutAnyValidMessagesIsRejected(): void {
		$catalog = $this->catalog([$this->provider([], ['messages' => [['content' => 42]]])]);
		try {
			$catalog->getPrompt('demo', []);
			$this->fail('Invalid prompt contents must be rejected.');
		}
		catch (\InvalidArgumentException $exception) {
			$this->assertStringContainsString('no messages', $exception->getMessage());
		}
	}

	private function provider(array $definitions, ?array $result = null): IAgentPromptProvider {
		$provider = $this->createStub(IAgentPromptProvider::class);
		$provider->method('getPromptDefinitions')->willReturn($definitions);
		$provider->method('getPrompt')->willReturn($result);
		return $provider;
	}

	private function catalog(array $providers): McpPromptCatalog {
		return new McpPromptCatalog($providers, $this->createStub(IAgentContext::class), $this->createStub(ILogger::class));
	}
}
