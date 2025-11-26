<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * PerplexityChatModelAgentResource v2
 *
 * Fully OpenAI-compatible wrapper for Perplexity.ai Chat API.
 * Adds robust tool-call extraction from natural language output,
 * including support for streaming mode.
 */
class PerplexityChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

        protected IAgentConfigValueResolver $resolver;

        protected array|string|null $modelConfig       = null;
        protected array|string|null $apikeyConfig      = null;
        protected array|string|null $endpointConfig    = null;
        protected array|string|null $temperatureConfig = null;

        protected array $resolvedOptions = [];

        public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
                parent::__construct($id);
                $this->resolver = $resolver;
        }

        public static function getName(): string {
                return 'perplexitychatmodelagentresource';
        }

        public function getDescription(): string {
                return 'Connects to Perplexity.ai Chat API (OpenAI-compatible) incl. natural-language tool-call parsing.';
        }

        /**
         * Load config from Flow JSON
         */
        public function setConfig(array $config): void {
                parent::setConfig($config);

                $this->modelConfig       = $config['model'] ?? null;
                $this->apikeyConfig      = $config['apikey'] ?? null;
                $this->endpointConfig    = $config['endpoint'] ?? null;
                $this->temperatureConfig = $config['temperature'] ?? null;

                $this->resolvedOptions = [
                        'model'       => $this->resolver->resolveValue($this->modelConfig) ?? 'pplx-70b-online',
                        'apikey'      => $this->resolver->resolveValue($this->apikeyConfig),
                        'endpoint'    => $this->resolver->resolveValue($this->endpointConfig)
                                ?? 'https://api.perplexity.ai/chat/completions',
                        'temperature' => (float)($this->resolver->resolveValue($this->temperatureConfig) ?? 0.7),
                ];
        }

        public function getOptions(): array {
                return $this->resolvedOptions;
        }

        public function setOptions(array $options): void {
                $this->resolvedOptions = array_merge($this->resolvedOptions, $options);
        }


        /**
         * BASIC CHAT
         */
        public function chat(array $messages): string {
                $result = $this->raw($messages);

                if (!isset($result['choices'][0]['message']['content'])) {
                        throw new \RuntimeException("Malformed Perplexity chat response: " . json_encode($result));
                }

                return $result['choices'][0]['message']['content'];
        }


        /**
         * ---- TOOL-CALL EXTRACTION ----
         */
        private function extractToolCallFromText(string $text): ?array {

                // CASE 1: {"name": "...", "arguments": {...}}
                if (preg_match('/\{[^{}]*"name"\s*:\s*"([^"]+)"[^{}]*"arguments"\s*:\s*(\{.*\})\}/s', $text, $m)) {
                        return [
                                'id' => uniqid('tool_', true),
                                'type' => 'function',
                                'function' => [
                                        'name' => $m[1],
                                        'arguments' => $m[2]
                                ]
                        ];
                }

                // CASE 2: name({...})
                if (preg_match('/([A-Za-z0-9_]+)\s*\(\s*(\{.*\})\s*\)/s', $text, $m)) {
                        return [
                                'id' => uniqid('tool_', true),
                                'type' => 'function',
                                'function' => [
                                        'name' => $m[1],
                                        'arguments' => $m[2]
                                ]
                        ];
                }

                // CASE 3: Tool call: name("text")
                if (preg_match('/Tool call:\s*([A-Za-z0-9_]+)\s*\(\s*"?([^"]+)"?\s*\)/i', $text, $m)) {
                        return [
                                'id' => uniqid('tool_', true),
                                'type' => 'function',
                                'function' => [
                                        'name' => $m[1],
                                        'arguments' => json_encode(["message" => $m[2]])
                                ]
                        ];
                }

                // CASE 4: <tool> {json} </tool>
                if (preg_match('/<tool>\s*(\{.*\})\s*<\/tool>/s', $text, $m)) {
                        $json = json_decode($m[1], true);
                        if (is_array($json) && isset($json['name'])) {
                                return [
                                        'id' => uniqid('tool_', true),
                                        'type' => 'function',
                                        'function' => [
                                                'name' => $json['name'],
                                                'arguments' => json_encode($json['arguments'] ?? [])
                                        ]
                                ];
                        }
                }

                return null;
        }


        /**
         * RAW REQUEST
         */
        public function raw(array $messages, array $tools = []): mixed {

                $model    = $this->resolvedOptions['model'];
                $apikey   = $this->resolvedOptions['apikey'];
                $endpoint = $this->resolvedOptions['endpoint'];
                $temp     = $this->resolvedOptions['temperature'];

                if (!$apikey) {
                        throw new \RuntimeException("Missing Perplexity API key.");
                }

                $normalized = $this->normalizeMessages($messages);

                $payload = [
                        'model'       => $model,
                        'messages'    => $normalized,
                        'temperature' => $temp,
                ];

                if (!empty($tools)) {
                        $payload['tools'] = $tools;
                        $payload['tool_choice'] = 'auto';
                }

                $jsonPayload = json_encode($payload);

                $headers = [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apikey
                ];

                $ch = curl_init($endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

                $result = curl_exec($ch);

                if (curl_errno($ch)) {
                        throw new \RuntimeException('Perplexity API request failed: ' . curl_error($ch));
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200) {
                        throw new \RuntimeException("Perplexity request failed with status $httpCode: $result");
                }

                $data = json_decode($result, true);
                if (!is_array($data)) {
                        throw new \RuntimeException("Invalid JSON response: " . substr($result, 0, 200));
                }

                //
                // ---- TOOL-CALL EXTRACTION FROM TEXT RESPONSE ----
                //
                $text = $data['choices'][0]['message']['content'] ?? '';
                $toolCall = $this->extractToolCallFromText($text);

                if ($toolCall !== null) {
                        $data['choices'][0]['message']['tool_calls'] = [$toolCall];
                }

                return $data;
        }


        /**
         * STREAMING IMPLEMENTATION
         */
        public function stream(
                array $messages,
                array $tools,
                callable $onData,
                callable $onMeta = null
        ): void {

                $model    = $this->resolvedOptions['model'];
                $apikey   = $this->resolvedOptions['apikey'];
                $endpoint = $this->resolvedOptions['endpoint'];
                $temp     = $this->resolvedOptions['temperature'];

                if (!$apikey) {
                        throw new \RuntimeException("Missing API key for Perplexity chat model.");
                }

                $normalized = $this->normalizeMessages($messages);

                $payload = [
                        'model'       => $model,
                        'messages'    => $normalized,
                        'temperature' => $temp,
                        'stream'      => true
                ];

                if (!empty($tools)) {
                        $payload['tools'] = $tools;
                        $payload['tool_choice'] = 'auto';
                }

                $jsonPayload = json_encode($payload);

                $headers = [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apikey
                ];

                $ch = curl_init($endpoint);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 0);

                curl_setopt($ch, CURLOPT_WRITEFUNCTION,
                        function ($ch, $chunk) use ($onData, $onMeta) {

                                $lines = preg_split("/\r\n|\n|\r/", $chunk);

                                foreach ($lines as $line) {
                                        $line = trim($line);

                                        if ($line === '' || !str_starts_with($line, 'data:')) {
                                                continue;
                                        }

                                        $data = trim(substr($line, 5));

                                        if ($data === '[DONE]') {
                                                if ($onMeta !== null) {
                                                        $onMeta(['event' => 'done']);
                                                }
                                                continue;
                                        }

                                        $json = json_decode($data, true);
                                        if (!is_array($json)) {
                                                continue;
                                        }

                                        $choice = $json['choices'][0] ?? [];

                                        // text streaming
                                        if (isset($choice['delta']['content'])) {
                                                $delta = $choice['delta']['content'];
                                                $onData($delta);

                                                // Detect tool-call patterns from streamed text
                                                $toolCall = $this->extractToolCallFromText($delta);
                                                if ($toolCall && $onMeta !== null) {
                                                        $onMeta([
                                                                'event' => 'toolcall',
                                                                'tool_calls' => [$toolCall]
                                                        ]);
                                                }
                                        }

                                        // finish reason
                                        if ($onMeta !== null && isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
                                                $onMeta([
                                                        'event' => 'meta',
                                                        'finish_reason' => $choice['finish_reason']
                                                ]);
                                        }
                                }

                                return strlen($chunk);
                        }
                );

                curl_exec($ch);
                curl_close($ch);
        }


        /**
         * Normalize messages for Perplexity (OpenAI style)
         */
        private function normalizeMessages(array $messages): array {
                $out = [];

                foreach ($messages as $m) {
                        if (!isset($m['role'])) continue;

                        $role    = $m['role'];
                        $content = $m['content'] ?? '';

                        // tool messages
                        if ($role === 'tool') {
                                $out[] = [
                                        'role'         => 'tool',
                                        'tool_call_id' => (string)($m['tool_call_id'] ?? ''),
                                        'content'      => is_string($content) ? $content : json_encode($content)
                                ];
                                continue;
                        }

                        // assistant + toolcalls
                        if ($role === 'assistant' && !empty($m['tool_calls'])) {
                                $toolCalls = [];

                                foreach ($m['tool_calls'] as $call) {
                                        $args = $call['function']['arguments'] ?? '{}';
                                        if (!is_string($args)) {
                                                $args = json_encode($args);
                                        }
                                        $toolCalls[] = [
                                                'id'       => $call['id'],
                                                'type'     => 'function',
                                                'function' => [
                                                        'name'      => $call['function']['name'],
                                                        'arguments' => $args
                                                ]
                                        ];
                                }

                                $out[] = [
                                        'role'       => 'assistant',
                                        'content'    => is_string($content) ? $content : json_encode($content),
                                        'tool_calls' => $toolCalls
                                ];
                                continue;
                        }

                        // standard messages
                        $out[] = [
                                'role'    => $role,
                                'content' => is_string($content) ? $content : json_encode($content)
                        ];

                        // feedback injection
                        if (!empty($m['feedback']) && is_string($m['feedback'])) {
                                $fb = trim($m['feedback']);
                                if ($fb !== '') {
                                        $out[] = [
                                                'role'    => 'user',
                                                'content' => $fb
                                        ];
                                }
                        }
                }

                return $out;
        }
}
