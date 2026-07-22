<?php declare(strict_types=1);

namespace MissionBay\Test\Transport;

use MissionBay\Transport\ChatCompletionEndpointResolver;
use PHPUnit\Framework\TestCase;

final class ChatCompletionEndpointResolverTest extends TestCase {

	/** @dataProvider provideEndpoints */
	public function testResolve(string $endpoint, string $path, string $expected): void {
		$this->assertSame($expected, ChatCompletionEndpointResolver::resolve($endpoint, $path));
	}

	/** @return array<string,array{string,string,string}> */
	public static function provideEndpoints(): array {
		return [
			'root base url' => [
				'https://api.openai.com',
				'/v1/chat/completions',
				'https://api.openai.com/v1/chat/completions'
			],
			'partial provider prefix' => [
				'https://api.groq.com/openai',
				'/v1/chat/completions',
				'https://api.groq.com/openai/v1/chat/completions'
			],
			'api version base url' => [
				'https://api.openai.com/v1',
				'/v1/chat/completions',
				'https://api.openai.com/v1/chat/completions'
			],
			'complete endpoint' => [
				'https://api.openai.com/v1/chat/completions',
				'/v1/chat/completions',
				'https://api.openai.com/v1/chat/completions'
			],
			'query endpoint is complete' => [
				'https://gateway.example.test/chat?api-version=1',
				'/v1/chat/completions',
				'https://gateway.example.test/chat?api-version=1'
			],
			'absolute service path' => [
				'https://ignored.example.test',
				'https://gateway.example.test/custom/chat',
				'https://gateway.example.test/custom/chat'
			]
		];
	}
}
