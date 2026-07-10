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

namespace MissionBay\Resource\AgentTool\CurrencyConvert;

use MissionBay\Api\IAgentTool;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Resource\AbstractAgentResource;

/**
 * CurrencyConvertAgentTool
 *
 * Converts an amount from one currency into another using the free
 * Frankfurter API (European Central Bank). No API key required.
 *
 * Supports:
 * - amount
 * - from (currency)
 * - to (currency)
 *
 * API docs: https://www.frankfurter.app/docs/
 */
class CurrencyConvertAgentTool extends AbstractAgentResource implements IAgentTool {

	public static function getName(): string {
		return 'currencyconvertagenttool';
	}

	public function getDescription(): string {
		return 'Converts a monetary value between currencies using the Frankfurter API.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Currency Conversion',
			'category' => 'lookup',
			'tags' => ['currency', 'convert', 'exchange_rate', 'forex'],
			'priority' => 50,
			'function' => [
				'name' => 'currency_convert',
				'description' => 'Converts an amount from one currency into another.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'from' => [
							'type' => 'string',
							'description' => 'Source currency (e.g. EUR, USD).'
						],
						'to' => [
							'type' => 'string',
							'description' => 'Target currency (e.g. USD, GBP).'
						],
						'amount' => [
							'type' => 'number',
							'description' => 'Amount to convert.'
						]
					],
					'required' => ['from', 'to', 'amount']
				]
			]
		]];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		if ($toolName !== 'currency_convert') {
			throw new \InvalidArgumentException("Unsupported tool: $toolName");
		}

		$from = strtoupper(trim($arguments['from'] ?? ''));
		$to = strtoupper(trim($arguments['to'] ?? ''));
		$amount = (float)($arguments['amount'] ?? 0);

		if ($from === '' || $to === '' || $amount <= 0) {
			return ['error' => 'Invalid or missing parameters.'];
		}

		// Construct Frankfurter API request
		$url = sprintf(
			'https://api.frankfurter.app/latest?amount=%s&from=%s&to=%s',
			$amount,
			urlencode($from),
			urlencode($to)
		);

		$json = @file_get_contents($url);
		if (!$json) {
			return ['error' => 'Currency conversion service unavailable'];
		}

		$data = json_decode($json, true);
		if (!isset($data['rates'][$to])) {
			return ['error' => 'Invalid response from conversion API'];
		}

		return [
			'query' => compact('from', 'to', 'amount'),
			'rate' => $data['rates'][$to],
			'result' => $data['rates'][$to], // Frankfurter already returns amount*rate
			'date' => $data['date'] ?? null,
			'base' => $data['base'] ?? null
		];
	}
}
