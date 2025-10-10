<?php declare(strict_types=1);

namespace MissionBay\Node\Ai;

use AssistentFoundation\Api\IAiChatModel;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentTool;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class AiAssistantNode extends AbstractAgentNode {

        protected ?ILogger $logger = null;

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
                                name: 'message',
                                description: 'The complete assistant message object (id, role, content, timestamp, feedback).',
                                type: 'array',
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
                                maxConnections: 99,
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
                /** @var IAgentMemory[] $memories */
                $memories = $resources['memory'] ?? [];
                /** @var IAgentTool[] $tools */
                $tools  = $resources['tools'] ?? [];

                if (isset($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
                        $this->logger = $resources['logger'][0];
                }

                if (!$model) {
                        $msg = 'Missing required chat model.';
                        $this->log('[ERROR] ' . $msg);
                        return ['error' => $this->error($msg)];
                }

                usort($memories, fn(IAgentMemory $a, IAgentMemory $b) => $a->getPriority() <=> $b->getPriority());

                $prompt = trim($inputs['prompt'] ?? '');
                $system = trim($inputs['system'] ?? 'You are a helpful assistant.');

                if ($prompt === '') {
                        $msg = 'Prompt is required.';
                        $this->log('[ERROR] ' . $msg);
                        return ['error' => $this->error($msg)];
                }

                // --- initialize messages ---
                // Wichtig: Der Node baut KEINE modell-spezifische Historie.
                // Er übergibt vollständige, "reiche" Objekte an das ChatModel.
                $messages = [['role' => 'system', 'content' => $system]];
                $nodeId   = $this->getId();

                // Vollständige History aus allen Memories übernehmen (ohne Transformation)
                foreach ($memories as $memory) {
                        $history = $memory->loadNodeHistory($nodeId);
                        $this->log("Loaded history entries from " . get_class($memory) . ": " . count($history));
                        foreach ($history as $entry) {
                                // Nur minimale Validierung; Rest obliegt der Resource (Modelladapter)
                                if (!isset($entry['role'])) {
                                        continue;
                                }
                                $messages[] = $entry; // unverändert übernehmen (inkl. id/timestamp/feedback)
                        }
                }

                // Aktuelle User-Nachricht als reiches Objekt erfassen und in die Liste aufnehmen
                $userMessage = [
                        'id'        => uniqid('msg_', true),
                        'role'      => 'user',
                        'content'   => $prompt,
                        'timestamp' => (new \DateTimeImmutable())->format('c'),
                        'feedback'  => null
                ];
                $messages[] = $userMessage;
                $this->log("User prompt appended (rich object).");

                // Tools sammeln
                $toolDefs = [];
                foreach ($tools as $tool) {
                        foreach ($tool->getToolDefinitions() as $def) {
                                $toolDefs[] = $def;
                        }
                }
                if (!empty($toolDefs)) {
                        $this->log("Tools registered: " . json_encode(array_column(array_column($toolDefs, 'function'), 'name')));
                }

                // --- tool loop ---
                $toolCalls = [];
                $assistantMessage = null;
                $loopGuard = 0;
                $maxLoops = 5;

                while ($loopGuard++ < $maxLoops) {
                        $this->log("Loop iteration $loopGuard, sending messages (" . count($messages) . " total)");

                        // WICHTIG: Übergabe der vollen Objekte an die Resource;
                        // diese versteht das LLM-Protokoll und normalisiert/transformiert.
                        $result = $model->raw($messages, $toolDefs);

                        if (!isset($result['choices'][0]['message'])) {
                                $msg = "Malformed model response";
                                $this->log("[ERROR] $msg");
                                return ['error' => $this->error($msg)];
                        }

                        $message = $result['choices'][0]['message'];
                        $messages[] = $message; // Modellantwort (roh) für evtl. weitere Tool-Schleifen

                        // Tool Calls?
                        if (isset($message['tool_calls'])) {
                                foreach ($message['tool_calls'] as $call) {
                                        $toolName = $call['function']['name'] ?? '';
                                        $args     = json_decode($call['function']['arguments'] ?? '{}', true) ?? [];

                                        $this->log("Tool call detected: $toolName " . json_encode($args));

                                        $tool = $this->findToolByName($tools, $toolName);
                                        if ($tool) {
                                                $result = $tool->callTool($toolName, $args, $context);
                                                $toolCalls[] = [
                                                        'tool' => $toolName,
                                                        'arguments' => $args,
                                                        'result' => $result
                                                ];
                                                $this->log("Tool result: " . json_encode($result));

                                                $messages[] = [
                                                        'role' => 'tool',
                                                        'tool_call_id' => $call['id'],
                                                        'content' => json_encode($result)
                                                ];
                                        } else {
                                                $this->log("[WARN] Tool not found: $toolName");
                                        }
                                }
                                continue;
                        }

                        // Finale Assistenten-Antwort in reiches Objekt verwandeln (für Memory)
                        $assistantMessage = [
                                'id'        => uniqid('msg_', true),
                                'role'      => 'assistant',
                                'content'   => $message['content'] ?? '',
                                'timestamp' => (new \DateTimeImmutable())->format('c'),
                                'feedback'  => null
                        ];
                        $this->log("Final response received, breaking loop.");
                        break;
                }

                // Memory: User & Assistant speichern (reiche Objekte)
                foreach ($memories as $memory) {
                        $memory->appendNodeHistory($nodeId, $userMessage);
                        if ($assistantMessage) {
                                $memory->appendNodeHistory($nodeId, $assistantMessage);
                        }
                }

                return [
                        'message'    => $assistantMessage,
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

        protected function log(string $message): void {
                if (!$this->logger) return;
                $fullMsg = '[' . $this->getName() . '|' . $this->getId() . '] ' . $message;
                $this->logger->log('AiAssistantNode', $fullMsg);
        }
}

