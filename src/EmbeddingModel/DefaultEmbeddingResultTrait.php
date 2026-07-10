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

namespace MissionBay\EmbeddingModel;

use AssistantFoundation\Dto\AiEmbeddingResult;
use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiUsage;

trait DefaultEmbeddingResultTrait {

	public function embedResult(array $texts): AiEmbeddingResult {
		$startedAt = microtime(true);
		$embeddings = $this->embed($texts);
		$options = $this->getOptions();
		$adapter = method_exists(static::class, 'getName') ? static::getName() : static::class;

		return new AiEmbeddingResult(
			$embeddings,
			new AiResultMetadata(
				'embedding',
				is_string($options['provider'] ?? null) ? $options['provider'] : '',
				is_string($options['model'] ?? null) ? $options['model'] : '',
				'',
				null,
				max(0.0, (microtime(true) - $startedAt) * 1000),
				null,
				new AiUsage(
					metrics: [
						'input_items' => count($texts),
						'output_vectors' => count($embeddings)
					]
				),
				['adapter' => $adapter]
			),
			null
		);
	}
}
