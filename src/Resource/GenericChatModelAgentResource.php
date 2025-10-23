<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistentFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * GenericChatModelAgentResource
 *
 * Provides access to any OpenAI-compatible Chat Completion API
 * (e.g. OpenAI, Mistral, Fireworks, Ollama).
 * Accepts structured message objects and normalizes them
 * to the OpenAI chat schema.
 */
class GenericChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

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
                return 'genericchatmodelagentresource';
        }

        public function getDescription(): string {
                return 'Connects to any OpenAI-compatible Chat Completion API (e.g. OpenAI, Mistral, Fireworks, Ollama). Supports function/tool-calling.';
        }

        public function setConfig(array $config): void {
                parent::setConfig($config);

                $this->modelConfig       = $config['model'] ?? null;
                $this->apikeyConfig      = $config['apikey'] ?? null;
                $this->endpointConfig    = $config['endpoint'] ?? null;
                $this->temperatureConfig = $config['temperature'] ?? null;
                $this->maxtokensConfig   = $config['maxtokens'] ?? null;

                $this->resolvedOptions = [
                        'model'       => $this->resolver->resolveValue($this->modelConfig) ?? 'gpt-4o-mini',
                        'apikey'      => $this->resolver->resolveValue($this->apikeyConfig),
                        'endpoint'    => $this->resolver->resolveValue($this->endpointConfig) ?? 'https://api.openai.com/v1/chat/completions',
                        'temperature' => (float)($this->resolver->resolveValue($this->temperatureConfig) ?? 0.7),
                        'maxtokens'   => (int)($this->resolver->resolveValue($this->maxtokensConfig) ?? 512),
                ];
        }

        public function getOptions(): array {
                return $this->resolvedOptions;
        }

        public function setOptions(array $options): void {
                $this->resolvedOptions = array_merge($this->resolvedOptions, $options);
        }

        /**
         * Sends chat messages and returns the assistant reply (string).
         */
        public function chat(array $messages): string {
                $result = $this->raw($messages);

                if (!isset($result['choices'][0]['message']['content'])) {
                        throw new \RuntimeException("Malformed chat response: " . json_encode($result));
                }

                return $result['choices'][0]['message']['content'];
        }

        /**
         * Sends chat messages and returns raw decoded JSON result.
         * Supports function/tool-calling if tools are provided.
         *
         * @param array $messages Structured message list
         * @param array $tools Optional tool definitions
         * @return mixed
         */
        public function raw(array $messages, array $tools = []): mixed {
                $model     = $this->resolvedOptions['model'] ?? 'gpt-4o-mini';
                $apikey    = $this->resolvedOptions['apikey'] ?? null;
                $endpoint  = $this->resolvedOptions['endpoint'] ?? 'https://api.openai.com/v1/chat/completions';
                $temp      = $this->resolvedOptions['temperature'] ?? 0.7;
                $maxtokens = $this->resolvedOptions['maxtokens'] ?? 512;

                if (!$apikey) {
                        throw new \RuntimeException("Missing API key for chat model.");
                }

                $normalized = $this->normalizeMessages($messages);

                $payload = [
                        'model'       => $model,
                        'messages'    => $normalized,
                        'temperature' => $temp,
                        'max_tokens'  => $maxtokens,
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
                        throw new \RuntimeException('Chat API request failed: ' . curl_error($ch));
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200) {
                        throw new \RuntimeException("Chat API request failed with status $httpCode: $result");
                }

                $data = json_decode($result, true);
                if (!is_array($data)) {
                        throw new \RuntimeException("Invalid JSON response from chat model: " . substr($result, 0, 200));
                }

                return $data;
        }

        /**
         * Normalizes structured messages into the standard OpenAI-compatible format.
         * Merges multiple system messages into a single one.
         */
        private function normalizeMessages(array $messages): array {
                $out = [];
                $systemContents = [];

                foreach ($messages as $m) {
                        if (!is_array($m) || !isset($m['role'])) {
                                continue;
                        }

                        $role    = $m['role'];
                        $content = $m['content'] ?? '';

                        // Collect all system messages for later merge
                        if ($role === 'system') {
                                if (is_string($content) && trim($content) !== '') {
                                        $systemContents[] = trim($content);
                                }
                                continue; // don't push yet
                        }

                        // handle tool message
                        if ($role === 'tool') {
                                if (empty($m['tool_call_id'])) {
                                        continue;
                                }
                                $out[] = [
                                        'role'         => 'tool',
                                        'tool_call_id' => (string)$m['tool_call_id'],
                                        'content'      => is_string($content) ? $content : json_encode($content),
                                ];
                        } elseif ($role === 'assistant' && !empty($m['tool_calls']) && is_array($m['tool_calls'])) {
                                $toolCalls = [];
                                foreach ($m['tool_calls'] as $call) {
                                        if (!isset($call['id'], $call['function']['name'])) {
                                                continue;
                                        }
                                        $args = $call['function']['arguments'] ?? '{}';
                                        if (is_array($args) || is_object($args)) {
                                                $args = json_encode($args);
                                        }
                                        $toolCalls[] = [
                                                'id'       => (string)$call['id'],
                                                'type'     => 'function',
                                                'function' => [
                                                        'name'      => (string)$call['function']['name'],
                                                        'arguments' => (string)$args,
                                                ],
                                        ];
                                }
                                $out[] = [
                                        'role'       => 'assistant',
                                        'content'    => is_string($content) ? $content : json_encode($content),
                                        'tool_calls' => $toolCalls,
                                ];
                        } else {
                                $out[] = [
                                        'role'    => $role,
                                        'content' => is_string($content) ? $content : json_encode($content),
                                ];
                        }

                        if (!empty($m['feedback']) && is_string($m['feedback'])) {
                                $fb = trim($m['feedback']);
                                if ($fb !== '') {
                                        $out[] = [
                                                'role'    => 'user',
                                                'content' => $fb,
                                        ];
                                }
                        }
                }

                // Merge all system messages into one, prepend to output
                if (!empty($systemContents)) {
                        $mergedSystem = implode("\n\n", $systemContents);
                        array_unshift($out, [
                                'role' => 'system',
                                'content' => $mergedSystem,
                        ]);
                }

                return $out;
        }
}

