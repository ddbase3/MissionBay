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

namespace MissionBay\Service;

use InvalidArgumentException;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlowFactory;
use AssistantFoundation\Api\IAiTaskService;

class MissionBayAiTaskService implements IAiTaskService {

	public function __construct(
		protected readonly IAgentContextFactory $contextFactory,
		protected readonly IAgentFlowFactory $flowFactory
	) {}

	public static function getName(): string {
		return 'missionbayaitaskservice';
	}

	public function run(string $systemPrompt, string $userPrompt, array $agentFlow): string {
		if ($agentFlow === []) {
			throw new InvalidArgumentException('Agent flow config must not be empty.');
		}

		$context = $this->contextFactory->createContext();
		$flow = $this->flowFactory->createFromArray('strictflow', $agentFlow, $context);

		$output = $flow->run($this->buildInputs($systemPrompt, $userPrompt));

		return $this->extractResponse($output);
	}

	/**
	 * Builds a minimal but compatible input set for common flow variants.
	 */
	protected function buildInputs(string $systemPrompt, string $userPrompt): array {
		return [
			'system' => $systemPrompt,
			'prompt' => $userPrompt,
			'user' => $userPrompt
		];
	}

	/**
	 * Extracts the final assistant response from common flow result shapes.
	 */
	protected function extractResponse(mixed $output): string {
		if (is_string($output)) {
			return trim($output);
		}

		$paths = [
			['assistant', 'message', 'content'],
			['message', 'content'],
			['assistant', 'content'],
			['content'],
			['text']
		];

		foreach ($paths as $path) {
			$value = $this->readPath($output, $path);
			if ($value !== null && $value !== '') {
				return trim($value);
			}
		}

		if (is_scalar($output)) {
			return trim((string) $output);
		}

		if (is_array($output)) {
			return (string) json_encode($output, JSON_UNESCAPED_UNICODE);
		}

		return '';
	}

	/**
	 * Reads a nested value from an array structure.
	 */
	protected function readPath(mixed $data, array $path): ?string {
		$current = $data;

		foreach ($path as $segment) {
			if (!is_array($current) || !array_key_exists($segment, $current)) {
				return null;
			}

			$current = $current[$segment];
		}

		if (is_string($current)) {
			return $current;
		}

		if (is_scalar($current)) {
			return (string) $current;
		}

		return null;
	}
}
