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

use MissionBay\Transport\OpenAiTransport;

class OpenAiEmbeddingModel extends AbstractEmbeddingModel {

	public static function getName(): string {
		return 'openaiembeddingmodel';
	}

	protected function getProviderName(): string {
		return OpenAiTransport::getName();
	}

	protected function getDefaultEndpoint(): string {
		return 'https://api.openai.com';
	}

	protected function getDefaultModel(): string {
		return 'text-embedding-3-small';
	}
}
