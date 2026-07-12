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

namespace MissionBay\Service\Assistant;

use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Orchestrator\AgentToolOrchestratorResult;

final class AgentAssistantFallbackBuilder implements IAgentAssistantFallbackBuilder {

	public function build(AgentToolOrchestratorResult $orchestrationResult): string {
		$finalAssistant = $orchestrationResult->getFinalAssistantMessage();
		if (is_array($finalAssistant) && trim((string)($finalAssistant['content'] ?? '')) !== '') {
			return trim((string)$finalAssistant['content']);
		}

		$lastAnswer = $this->findLastSuccessfulAnswer($orchestrationResult);
		if ($lastAnswer !== '') {
			return $lastAnswer;
		}

		$lastUrl = $this->findLastSuccessfulUrl($orchestrationResult);
		if ($lastUrl !== '') {
			return "Ich konnte die Tool-Phase nicht vollständig abschließen, aber der zuletzt erfolgreich erzeugte Link ist:
" . $lastUrl;
		}

		$lastError = $this->findLastToolError($orchestrationResult);
		if ($lastError !== '') {
			return 'Ich konnte die Anfrage nicht vollständig abschließen. Letzter Tool-Hinweis: ' . $lastError;
		}

		if ($orchestrationResult->hasFailure()) {
			$message = $orchestrationResult->getFailureMessage();
			if ($message === '') {
				$message = $orchestrationResult->getFailureCode();
			}

			return 'Ich konnte die Anfrage nicht vollständig abschließen. Grund: ' . $message;
		}

		return 'Ich konnte die Anfrage nicht vollständig abschließen. Bitte versuche es erneut oder grenze die Anfrage etwas ein.';
	}

	private function findLastSuccessfulAnswer(AgentToolOrchestratorResult $orchestrationResult): string {
		$toolCalls = array_reverse($orchestrationResult->getToolCalls());
		foreach ($toolCalls as $call) {
			$result = $call['result'] ?? null;
			if (!is_array($result) || ($result['ok'] ?? false) !== true) {
				continue;
			}

			$answer = $result['answer'] ?? null;
			if (is_scalar($answer) && trim((string)$answer) !== '') {
				return trim((string)$answer);
			}
		}

		return '';
	}

	private function findLastSuccessfulUrl(AgentToolOrchestratorResult $orchestrationResult): string {
		$toolCalls = array_reverse($orchestrationResult->getToolCalls());
		foreach ($toolCalls as $call) {
			$result = $call['result'] ?? null;
			if (!is_array($result)) {
				continue;
			}

			if (($result['ok'] ?? false) === true && trim((string)($result['url'] ?? '')) !== '') {
				return trim((string)$result['url']);
			}
		}

		return '';
	}

	private function findLastToolError(AgentToolOrchestratorResult $orchestrationResult): string {
		$toolCalls = array_reverse($orchestrationResult->getToolCalls());
		foreach ($toolCalls as $call) {
			if (trim((string)($call['error'] ?? '')) !== '') {
				return trim((string)$call['error']);
			}

			$result = $call['result'] ?? null;
			if (!is_array($result)) {
				continue;
			}

			if (($result['ok'] ?? true) === false) {
				$errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
				foreach ($errors as $error) {
					if (is_array($error) && trim((string)($error['message'] ?? '')) !== '') {
						return trim((string)$error['message']);
					}
				}

				if (trim((string)($result['message'] ?? '')) !== '') {
					return trim((string)$result['message']);
				}

				if (trim((string)($result['error'] ?? '')) !== '') {
					return trim((string)$result['error']);
				}
			}
		}

		return '';
	}
}
