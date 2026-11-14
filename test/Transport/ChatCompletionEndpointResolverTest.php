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

namespace Base3\MissionBay\Test\Transport;

use Base3\MissionBay\Transport\ChatCompletionEndpointResolver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ChatCompletionEndpointResolverTest extends TestCase {

	#[DataProvider('provideEndpoints')]
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
