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

namespace MissionBay\Orchestrator;

use AssistantFoundation\Dto\AgentAction;

/** Binds approval to an exact canonical action payload. */
final class AgentActionFingerprint {

	public function create(AgentAction $action): string {
		$payload = [
			'type' => $action->getType(),
			'name' => $action->getName(),
			'input' => $this->canonicalize($action->getInput())
		];
		$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($json)) {
			throw new \RuntimeException('Agent action could not be serialized for approval fingerprinting.');
		}
		return hash('sha256', $json);
	}

	private function canonicalize(mixed $value): mixed {
		if (!is_array($value)) {
			return $value;
		}
		if (array_is_list($value)) {
			return array_map(fn(mixed $entry): mixed => $this->canonicalize($entry), $value);
		}
		ksort($value, SORT_STRING);
		foreach ($value as $key => $entry) {
			$value[$key] = $this->canonicalize($entry);
		}
		return $value;
	}
}
