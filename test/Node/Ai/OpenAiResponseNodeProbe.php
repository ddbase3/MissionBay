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

namespace Base3\MissionBay\Test\Node\Ai;

use Base3\MissionBay\Node\Ai\OpenAiResponseNode;

final class OpenAiResponseNodeProbe extends OpenAiResponseNode {

	/** @var array<int,array{api_key:string,model:string,messages:array}> */
	public array $requests = [];

	public string $reply = 'Generated answer';

	public function renderPrompt(array $history, string $message): string {
		return $this->buildPrompt($history, $message);
	}

	protected function callOpenAi(string $apiKey, string $model, array $chatMessages): string {
		$this->requests[] = [
			'api_key' => $apiKey,
			'model' => $model,
			'messages' => $chatMessages
		];
		return $this->reply;
	}
}
