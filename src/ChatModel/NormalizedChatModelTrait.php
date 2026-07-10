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

namespace MissionBay\ChatModel;

use AssistantFoundation\Dto\AiChatResult;
use MissionBay\Ai\AiResultNormalizer;

trait NormalizedChatModelTrait {

	public function complete(array $messages, array $tools = []): AiChatResult {
		$startedAt = microtime(true);
		$raw = $this->raw($messages, $tools);

		return AiResultNormalizer::chat($raw, $this->buildResultHints($startedAt));
	}

	public function streamResult(
		array $messages,
		array $tools,
		callable $onData,
		callable $onMeta = null
	): AiChatResult {
		$startedAt = microtime(true);
		$content = '';
		$metadataEvents = [];

		$this->stream(
			$messages,
			$tools,
			function(string $delta) use (&$content, $onData): void {
				$content .= $delta;
				$onData($delta);
			},
			function(array $metadata) use (&$metadataEvents, $onMeta): void {
				$metadataEvents[] = $metadata;

				if($onMeta !== null) {
					$onMeta($metadata);
				}
			}
		);

		return new AiChatResult(
			$content,
			[],
			AiResultNormalizer::streamMetadata(
				$metadataEvents,
				$this->buildResultHints($startedAt),
				$startedAt
			),
			$metadataEvents
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildResultHints(float $startedAt): array {
		$options = $this->getOptions();
		$provider = is_string($options['provider'] ?? null) ? $options['provider'] : '';

		if($provider === '' && method_exists($this, 'getProviderName')) {
			$provider = (string)$this->getProviderName();
		}

		return [
			'provider' => $provider,
			'model' => is_string($options['model'] ?? null) ? $options['model'] : '',
			'adapter' => method_exists(static::class, 'getName') ? static::getName() : static::class,
			'started_at' => $startedAt
		];
	}
}
