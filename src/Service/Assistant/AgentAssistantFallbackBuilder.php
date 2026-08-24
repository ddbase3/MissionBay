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
                        return "Ich konnte die Tool-Phase nicht vollständig abschließen, aber der zuletzt erfolgreich erzeugte Link ist:\n" . $lastUrl;
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

                        $technicalCause = $this->findSafeTechnicalCause($orchestrationResult);

                        if ($technicalCause !== '') {
                                return 'Ich konnte die Anfrage nicht vollständig abschließen. Grund: '
                                        . $message
                                        . ' Technische Ursache: '
                                        . $technicalCause;
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
                        $callError = $this->extractErrorMessage($call['error'] ?? null);
                        if ($callError !== '') {
                                return $callError;
                        }

                        $result = $call['result'] ?? null;
                        if (!is_array($result) || ($result['ok'] ?? true) !== false) {
                                continue;
                        }

                        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
                        foreach ($errors as $error) {
                                $message = $this->extractErrorMessage($error);
                                if ($message !== '') {
                                        return $message;
                                }
                        }

                        $message = $this->extractErrorMessage($result['message'] ?? null);
                        if ($message !== '') {
                                return $message;
                        }

                        $error = $this->extractErrorMessage($result['error'] ?? null);
                        if ($error !== '') {
                                return $error;
                        }
                }

                return '';
        }

        private function extractErrorMessage(mixed $error): string {
                if (is_scalar($error)) {
                        return trim((string)$error);
                }

                if ($error instanceof \Stringable) {
                        return trim((string)$error);
                }

                if (!is_array($error)) {
                        return '';
                }

                foreach (['message', 'error', 'detail', 'reason'] as $key) {
                        if (!array_key_exists($key, $error)) {
                                continue;
                        }

                        $message = $this->extractErrorMessage($error[$key]);
                        if ($message !== '') {
                                return $message;
                        }
                }

                foreach ($error as $value) {
                        $message = $this->extractErrorMessage($value);
                        if ($message !== '') {
                                return $message;
                        }
                }

                return '';
        }

        private function findSafeTechnicalCause(AgentToolOrchestratorResult $orchestrationResult): string {
                $detail = $orchestrationResult->getFailureDetail();
                $message = is_scalar($detail['message'] ?? null)
                        ? trim((string)$detail['message'])
                        : '';

                if ($message === '' || !str_starts_with($message, 'Local LLM')) {
                        return '';
                }

                $message = preg_replace('/Bearer\s+[^\s,;]+/i', 'Bearer [REDACTED]', $message) ?? $message;

                return mb_substr($message, 0, 2000);
        }

}
