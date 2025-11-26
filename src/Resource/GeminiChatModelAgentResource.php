<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * GeminiChatModelAgentResource
 *
 * Drop-in replacement compatible with OpenAiChatModelAgentResource
 * Produces OpenAI-compatible outputs (choices/message/etc),
 * but behind the scenes calls Google's Gemini v1beta API.
 */
class GeminiChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

        protected IAgentConfigValueResolver $resolver;

        protected array|string|null $modelConfig = null;
        protected array|string|null $apikeyConfig = null;
        protected array|string|null $endpointConfig = null;
        protected array|string|null $temperatureConfig = null;
        protected array|string|null $maxtokensConfig = null;

        protected array $resolvedOptions = [];

        public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
                parent::__construct($id);
                $this->resolver = $resolver;
        }

        public static function getName(): string {
                return 'geminichatmodelagentresource';
        }

        public function getDescription(): string {
                return 'Connects to Google Gemini API with full OpenAI-style output compatibility.';
        }

        /**
         * Load config (same pattern as OpenAI version)
         */
        public function setConfig(array $config): void {
                parent::setConfig($config);

                $this->modelConfig       = $config['model'] ?? null;
                $this->apikeyConfig      = $config['apikey'] ?? null;
                $this->endpointConfig    = $config['endpoint'] ?? null;
                $this->temperatureConfig = $config['temperature'] ?? null;
                $this->maxtokensConfig   = $config['maxtokens'] ?? null;

                $this->resolvedOptions = [
                        'model'       => $this->resolver->resolveValue($this->modelConfig) ?? 'gemini-1.5-flash',
                        'apikey'      => $this->resolver->resolveValue($this->apikeyConfig),
                        'endpoint'    => $this->resolver->resolveValue($this->endpointConfig)
                                ?? 'https://generativelanguage.googleapis.com/v1beta/models',
                        'temperature' => (float)($this->resolver->resolveValue($this->temperatureConfig) ?? 0.7),
                        'maxtokens'   => (int)($this->resolver->resolveValue($this->maxtokensConfig) ?? 4096),
                ];
        }

        public function getOptions(): array {
                return $this->resolvedOptions;
        }

        public function setOptions(array $options): void {
                $this->resolvedOptions = array_merge($this->resolvedOptions, $options);
        }

        /**
         * OPENAI-COMPATIBLE CHAT OUTPUT
         */
        public function chat(array $messages): string {
                $result = $this->raw($messages);

                if (!isset($result['choices'][0]['message']['content'])) {
                        throw new \RuntimeException("Malformed Gemini(OpenAI-mode) response: " . json_encode($result));
                }

                return $result['choices'][0]['message']['content'];
        }


        /**
         * Convert OpenAI tools → Gemini functionDeclarations
         */
        private function normalizeTools(array $tools): array {
                $geminiTools = [];

                foreach ($tools as $t) {
                        if (isset($t['function'])) {
                                $fn = $t['function'];

                                $geminiTools[] = [
                                        'name' => $fn['name'] ?? '',
                                        'description' => $fn['description'] ?? '',
                                        'parameters' => $fn['parameters'] ?? [
                                                'type' => 'object',
                                                'properties' => []
                                        ]
                                ];
                        }
                }

                return $geminiTools;
        }


        /**
         * RAW Gemini-call → OpenAI-compatible response
         */
        public function raw(array $messages, array $tools = []): mixed {

                $apikey   = $this->resolvedOptions['apikey'];
                $endpoint = $this->resolvedOptions['endpoint'];
                $model    = $this->resolvedOptions['model'];
                $temp     = $this->resolvedOptions['temperature'];
                $maxtokens = $this->resolvedOptions['maxtokens'];

                if (!$apikey) {
                        throw new \RuntimeException("Missing Gemini API key.");
                }

                $normalized = $this->normalizeMessages($messages);

                $payload = [
                        'contents' => $normalized,
                        'generationConfig' => [
                                'temperature' => $temp,
                                'maxOutputTokens' => $maxtokens
                        ]
                ];

                if (!empty($tools)) {
                        $payload['tools'] = [
                                [
                                        'functionDeclarations' => $this->normalizeTools($tools)
                                ]
                        ];
                }

                $jsonPayload = json_encode($payload);

                $url = $endpoint . '/' . $model . ':generateContent?key=' . urlencode($apikey);

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

                $result = curl_exec($ch);

                if (curl_errno($ch)) {
                        throw new \RuntimeException('Gemini request failed: ' . curl_error($ch));
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200) {
                        throw new \RuntimeException("Gemini error $httpCode: $result");
                }

                $data = json_decode($result, true);
                if (!is_array($data)) {
                        throw new \RuntimeException("Invalid Gemini JSON: " . substr($result ?? '', 0, 200));
                }

                // ---- OPENAI-COMPATIBLE WRAPPING ----

                $candidate = $data['candidates'][0] ?? [];

                $parts = $candidate['content']['parts'][0] ?? [];
                $text = $parts['text'] ?? '';
                $toolCall = $parts['functionCall'] ?? null;

                $message = [
                        'role' => 'assistant',
                        'content' => $text,
                ];

                if ($toolCall) {
                        $message['tool_calls'] = [
                                [
                                        'id' => uniqid('tool_', true),
                                        'type' => 'function',
                                        'function' => [
                                                'name' => $toolCall['name'] ?? '',
                                                'arguments' => json_encode($toolCall['args'] ?? [])
                                        ]
                                ]
                        ];
                }

                return [
                        'choices' => [
                                [
                                        'message' => $message,
                                        'finish_reason' => $candidate['finishReason'] ?? 'stop'
                                ]
                        ]
                ];
        }


        /**
         * STREAMING Gemini → OpenAI-style SSE-compatible
         */
        public function stream(
                array $messages,
                array $tools,
                callable $onData,
                callable $onMeta = null
        ): void {

                $apikey   = $this->resolvedOptions['apikey'];
                $endpoint = $this->resolvedOptions['endpoint'];
                $model    = $this->resolvedOptions['model'];
                $temp     = $this->resolvedOptions['temperature'];
                $maxtokens = $this->resolvedOptions['maxtokens'];

                if (!$apikey) {
                        throw new \RuntimeException("Missing Gemini API key.");
                }

                $normalized = $this->normalizeMessages($messages);

                $payload = [
                        'contents' => $normalized,
                        'generationConfig' => [
                                'temperature' => $temp,
                                'maxOutputTokens' => $maxtokens
                        ],
                        'stream' => true
                ];

                if (!empty($tools)) {
                        $payload['tools'] = [
                                [
                                        'functionDeclarations' => $this->normalizeTools($tools)
                                ]
                        ];
                }

                $jsonPayload = json_encode($payload);

                $url = $endpoint . '/' . $model . ':streamGenerateContent?key=' . urlencode($apikey);

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 0);

                curl_setopt(
                        $ch,
                        CURLOPT_WRITEFUNCTION,
                        function ($ch, $chunk) use ($onData, $onMeta) {

                                $lines = preg_split("/\r\n|\n|\r/", $chunk);

                                foreach ($lines as $line) {
                                        $line = trim($line);
                                        if ($line === '') {
                                                continue;
                                        }

                                        $json = json_decode($line, true);
                                        if (!is_array($json)) {
                                                continue;
                                        }

                                        $candidate = $json['candidates'][0] ?? null;
                                        if (!$candidate) {
                                                continue;
                                        }

                                        $parts = $candidate['content']['parts'][0] ?? [];

                                        // text chunk
                                        if (isset($parts['text'])) {
                                                $onData($parts['text']);
                                        }

                                        // tool call
                                        if (isset($parts['functionCall']) && $onMeta !== null) {
                                                $onMeta([
                                                        'event' => 'toolcall',
                                                        'tool_calls' => [
                                                                [
                                                                        'id' => uniqid('tool_', true),
                                                                        'type' => 'function',
                                                                        'function' => [
                                                                                'name' => $parts['functionCall']['name'] ?? '',
                                                                                'arguments' => json_encode($parts['functionCall']['args'] ?? [])
                                                                        ]
                                                                ]
                                                        ]
                                                ]);
                                        }

                                        // finish reason
                                        if ($onMeta !== null && isset($candidate['finishReason'])) {
                                                $onMeta([
                                                        'event' => 'meta',
                                                        'finish_reason' => $candidate['finishReason']
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
         * Normalize messages to Gemini format, but accept OpenAI-format input.
         */
        private function normalizeMessages(array $messages): array {
                $out = [];

                foreach ($messages as $m) {
                        if (!isset($m['role'])) {
                                continue;
                        }

                        $role = $m['role'];
                        $content = $m['content'] ?? '';

                        // Gemini supports only: user, model
                        // system + tool must be converted to user instructions

                        if ($role === 'system' || $role === 'tool') {

                                $prefix = ($role === 'tool')
                                        ? "Tool output:"
                                        : "System instruction:";

                                $text = is_string($content)
                                        ? $content
                                        : json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                                $out[] = [
                                        'role' => 'user',
                                        'parts' => [
                                                ['text' =>
                                                        $prefix . "\n\n"
                                                        . $text
                                                        . "\n\nPlease follow this information."
                                                ]
                                        ]
                                ];
                                continue;
                        }

                        // regular assistant/user messages
                        $out[] = [
                                'role' => $role === 'assistant' ? 'model' : 'user',
                                'parts' => [
                                        [
                                                'text' => is_string($content) ? $content : json_encode($content)
                                        ]
                                ]
                        ];
                }

                return $out;
        }
}
