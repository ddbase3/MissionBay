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

namespace Base3\MissionBay\Resource\AgentTool\CurrencyConvert;

/**
 * Namespaced override for file_get_contents() to avoid real HTTP calls in tests.
 * PHP resolves file_get_contents() inside Base3\MissionBay\Resource\* to this function first.
 */


function file_get_contents(string $filename, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null): string|false {
	FakeHttpState::$lastUrl = $filename;
	return FakeHttpState::$nextResponse ?? false;
}


namespace Base3\MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\MissionBay\Resource\AgentTool\CurrencyConvert\CurrencyConvertAgentTool;
use Base3\MissionBay\Resource\AgentTool\CurrencyConvert\FakeHttpState;
use Base3\MissionBay\Api\IAgentTool;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentMemory;

class CurrencyConvertAgentToolTest extends TestCase {

	protected function setUp(): void {
		FakeHttpState::reset();
	}

	public function testImplementsAgentToolInterface(): void {
		$tool = new CurrencyConvertAgentTool('id1');
		$this->assertInstanceOf(IAgentTool::class, $tool);
	}

	public function testGetNameAndDescription(): void {
		$tool = new CurrencyConvertAgentTool('id1');

		$this->assertSame('currencyconvertagenttool', CurrencyConvertAgentTool::getName());
		$this->assertSame(
			'Converts a monetary value between currencies using the Frankfurter API.',
			$tool->getDescription()
		);
	}

	public function testGetToolDefinitionsReturnsExpectedSchema(): void {
		$tool = new CurrencyConvertAgentTool('id1');

		$defs = $tool->getToolDefinitions();

		$this->assertIsArray($defs);
		$this->assertCount(1, $defs);

		$def = $defs[0];

		$this->assertSame('function', $def['type']);
		$this->assertSame('Currency Conversion', $def['label']);

		$this->assertIsArray($def['function']);
		$this->assertSame('currency_convert', $def['function']['name']);
		$this->assertSame('Converts an amount from one currency into another.', $def['function']['description']);

		$params = $def['function']['parameters'] ?? null;
		$this->assertIsArray($params);
		$this->assertSame('object', $params['type']);

		$props = $params['properties'] ?? null;
		$this->assertIsArray($props);
		$this->assertSame('string', $props['from']['type']);
		$this->assertSame('string', $props['to']['type']);
		$this->assertSame('number', $props['amount']['type']);

		$this->assertSame(['from', 'to', 'amount'], $params['required']);
	}

	public function testCallToolThrowsForUnsupportedToolName(): void {
		$tool = new CurrencyConvertAgentTool('id1');
		$context = new CurrencyConvertAgentContextStub();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported tool: no');

		$tool->callTool('no', [], $context);
	}

	public function testCallToolReturnsErrorForInvalidOrMissingParameters(): void {
		$tool = new CurrencyConvertAgentTool('id1');
		$context = new CurrencyConvertAgentContextStub();

		$this->assertSame(
			['error' => 'Invalid or missing parameters.'],
			$tool->callTool('currency_convert', [], $context)
		);

		$this->assertSame(
			['error' => 'Invalid or missing parameters.'],
			$tool->callTool('currency_convert', ['from' => 'EUR', 'to' => 'USD', 'amount' => 0], $context)
		);

		$this->assertSame(
			['error' => 'Invalid or missing parameters.'],
			$tool->callTool('currency_convert', ['from' => ' ', 'to' => 'USD', 'amount' => 10], $context)
		);
	}

	public function testCallToolReturnsServiceUnavailableIfRequestFails(): void {
		$tool = new CurrencyConvertAgentTool('id1');
		$context = new CurrencyConvertAgentContextStub();

		FakeHttpState::$nextResponse = false;

		$result = $tool->callTool('currency_convert', [
			'from' => 'eur',
			'to' => 'usd',
			'amount' => 10
		], $context);

		$this->assertSame(['error' => 'Currency conversion service unavailable'], $result);
		$this->assertIsString(FakeHttpState::$lastUrl);

		$this->assertStringContainsString('https://api.frankfurter.app/latest?', FakeHttpState::$lastUrl);
		$this->assertStringContainsString('from=EUR', FakeHttpState::$lastUrl);
		$this->assertStringContainsString('to=USD', FakeHttpState::$lastUrl);
		$this->assertStringContainsString('amount=10', FakeHttpState::$lastUrl);
	}

	public function testCallToolReturnsInvalidResponseIfRateMissing(): void {
		$tool = new CurrencyConvertAgentTool('id1');
		$context = new CurrencyConvertAgentContextStub();

		FakeHttpState::$nextResponse = json_encode([
			'date' => '2025-01-01',
			'base' => 'EUR',
			'rates' => [
				'GBP' => 0.8
			]
		]);

		$result = $tool->callTool('currency_convert', [
			'from' => 'EUR',
			'to' => 'USD',
			'amount' => 10
		], $context);

		$this->assertSame(['error' => 'Invalid response from conversion API'], $result);
	}

	public function testCallToolReturnsSuccessPayload(): void {
		$tool = new CurrencyConvertAgentTool('id1');
		$context = new CurrencyConvertAgentContextStub();

		FakeHttpState::$nextResponse = json_encode([
			'amount' => 12.5,
			'base' => 'EUR',
			'date' => '2025-02-03',
			'rates' => [
				'USD' => 13.75
			]
		]);

		$result = $tool->callTool('currency_convert', [
			'from' => ' eur ',
			'to' => ' usd ',
			'amount' => 12.5
		], $context);

		$this->assertSame([
			'query' => [
				'from' => 'EUR',
				'to' => 'USD',
				'amount' => 12.5
			],
			'rate' => 13.75,
			'result' => 13.75,
			'date' => '2025-02-03',
			'base' => 'EUR'
		], $result);

		$this->assertIsString(FakeHttpState::$lastUrl);
		$this->assertStringContainsString('from=EUR', FakeHttpState::$lastUrl);
		$this->assertStringContainsString('to=USD', FakeHttpState::$lastUrl);
		$this->assertStringContainsString('amount=12.5', FakeHttpState::$lastUrl);
	}

}

require_once __DIR__ . '/FakeHttpState.php';
require_once __DIR__ . '/CurrencyConvertAgentContextStub.php';
require_once __DIR__ . '/CurrencyConvertAgentMemoryStub.php';
