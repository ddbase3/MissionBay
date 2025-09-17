<?php declare(strict_types=1);

namespace MissionBay\Node\Ai;

use Base3\Api\IAiChatModel;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentTool;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class AiAssistantNode extends AbstractAgentNode {

        public static function getName(): string {
                return 'aiassistantnode';
        }

        public function getDescription(): string {
                return 'Sends a user prompt to a docked chat model and returns the assistant response. Supports memory context and callable tools with iterative tool-calling (debug logging enabled).';
        }

        public function getInputDefinitions(): array {
                return [
                        new AgentNodePort(
                                name: 'prompt',
                                description: 'The user\'s message to the assistant.',
                                type: 'string',
                                default: null,
                                required: true
                        ),
                        new AgentNodePort(
                                name: 'system',
                                description: 'Optional system message to guide assistant behavior.',
                                type: 'string',
                                default: 'You are a helpful assistant.',
                                required: false
                        )
                ];
        }

        public function getOutputDefinitions(): array {
                return [
                        new AgentNodePort(
                                name: 'response',
                                description: 'Final assistant reply (after tool usage).',
                                type: 'string',
                                default: null,
                                required: false
                        ),
                        new AgentNodePort(
                                name: 'tool_calls',
                                description: 'List of tool calls executed during this interaction.',
                                type: 'array',
                                default: [],
                                required: false
                        ),
                        new AgentNodePort(
                                name: 'error',
                                description: 'Error message, if any.',
                                type: 'string',
                                default: null,
                                required: false
                        )
                ];
        }

        public function getDockDefinitions(): array {
                return [
                        new AgentNodeDock(
                                name: 'chatmodel',
                                description: 'Docked assistant chat model.',
                                interface: IAiChatModel::class,
                                maxConnections: 1,
                                required: true
                        ),
                        new AgentNodeDock(
                                name: 'memory',
                                description: 'Optional memory for storing previous messages.',
                                interface: IAgentMemory::class,
                                maxConnections: 1,
                                required: false
                        ),
                        new AgentNodeDock(
                                name: 'logger',
                                description: 'Optional logger for events and errors.',
                                interface: ILogger::class,
                                maxConnections: 1,
                                required: false
                        ),
                        new AgentNodeDock(
                                name: 'tools',
                                description: 'Optional tools callable by the assistant.',
                                interface: IAgentTool::class,
                                maxConnections: 99,
                                required: false
                        )
                ];
        }

        public function execute(array $inputs, array $resources, IAgentContext $context): array {
                /** @var IAiChatModel|null $model */
                $model  = $resources['chatmodel'][0] ?? null;
                /** @var IAgentMemory|null $memory */
                $memory = $resources['memory'][0] ?? null;
                /** @var ILogger|null $logger */
                $logger = $resources['logger'][0] ?? null;
                /** @var IAgentTool[] $tools */
                $tools  = $resources['tools'] ?? [];

                $scope = 'aiassistant';

                if (!$model) {
                        $msg = 'Missing required chat model.';
                        if ($logger) $logger->log($scope, '[ERROR] ' . $msg);
                        return ['error' => $this->error($msg)];
                }

                $prompt = trim($inputs['prompt'] ?? '');
                $system = trim($inputs['system'] ?? 'You are a helpful assistant.');

                if ($prompt === '') {
                        $msg = 'Prompt is required.';
                        if ($logger) $logger->log($scope, '[ERROR] ' . $msg);
                        return ['error' => $this->error($msg)];
                }

                // --- Messages initialisieren ---
                $messages = [['role' => 'system', 'content' => $system]];
                $nodeId   = $this->getId();

                // Memory laden
                if ($memory) {
                        $history = $memory->loadNodeHistory($nodeId);
                        if ($logger) $logger->log($scope, "Loaded history entries: " . count($history));
                        foreach ($history as [$role, $text]) {
                                $messages[] = ['role' => $role, 'content' => $text];
                        }
                }

                // User input
                $messages[] = ['role' => 'user', 'content' => $prompt];
                if ($logger) $logger->log($scope, "User prompt appended: $prompt");

                // Tools sammeln
                $toolDefs = [];
                foreach ($tools as $tool) {
                        foreach ($tool->getToolDefinitions() as $def) {
                                $toolDefs[] = $def;
                        }
                }
                if ($logger && !empty($toolDefs)) {
                        $logger->log($scope, "Tools registered: " . json_encode(array_column(array_column($toolDefs, 'function'), 'name')));
                }

                // --- Tool-Loop ---
                $toolCalls = [];
                $finalResponse = null;
                $loopGuard = 0;
                $maxLoops = 5;

                while ($loopGuard++ < $maxLoops) {
                        if ($logger) {
                                $logger->log($scope, "Loop iteration $loopGuard, sending messages (" . count($messages) . " total)");
                                $logger->log($scope, "Messages before API call:\n" . json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                        }

                        // API Call
                        $result = $model->raw($messages, $toolDefs);

                        if (!isset($result['choices'][0]['message'])) {
                                $msg = "Malformed model response";
                                if ($logger) $logger->log($scope, "[ERROR] $msg");
                                return ['error' => $this->error($msg)];
                        }

                        $message = $result['choices'][0]['message'];
                        $messages[] = $message; // Assistant-Antwort immer in History aufnehmen

                        // Prüfen ob tool_calls enthalten sind
                        if (isset($message['tool_calls'])) {
                                foreach ($message['tool_calls'] as $call) {
                                        $toolName = $call['function']['name'] ?? '';
                                        $args     = json_decode($call['function']['arguments'] ?? '{}', true) ?? [];

                                        if ($logger) $logger->log($scope, "Tool call detected: $toolName " . json_encode($args));

                                        $tool = $this->findToolByName($tools, $toolName);
                                        if ($tool) {
                                                $result = $tool->callTool($toolName, $args, $context);
                                                $toolCalls[] = [
                                                        'tool' => $toolName,
                                                        'arguments' => $args,
                                                        'result' => $result
                                                ];
                                                if ($logger) $logger->log($scope, "Tool result: " . json_encode($result));

                                                // Tool-Antwort korrekt anfügen
                                                $messages[] = [
                                                        'role' => 'tool',
                                                        'tool_call_id' => $call['id'],
                                                        'content' => json_encode($result)
                                                ];
                                        } else {
                                                if ($logger) $logger->log($scope, "[WARN] Tool not found: $toolName");
                                        }
                                }

                                // Nach Tool-Aufruf neue Iteration
                                continue;
                        }

                        // Normale Antwort → fertig
                        $finalResponse = $message['content'] ?? '';
                        if ($logger) {
                                $logger->log($scope, "Final response received, breaking loop.");
                                // $logger->log($scope, "Final Messages basis for response:\n" . json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                        }
                        break;
                }

                // Memory speichern
                if ($memory) {
                        $memory->appendNodeHistory($nodeId, 'user', $prompt);
                        if ($finalResponse) {
                                $memory->appendNodeHistory($nodeId, 'assistant', $finalResponse);
                        }
                }

                return [
                        'response'   => $finalResponse,
                        'tool_calls' => $toolCalls
                ];
        }

        private function findToolByName(array $tools, string $name): ?IAgentTool {
                foreach ($tools as $tool) {
                        foreach ($tool->getToolDefinitions() as $def) {
                                if (($def['function']['name'] ?? '') === $name) {
                                        return $tool;
                                }
                        }
                }
                return null;
        }
}

